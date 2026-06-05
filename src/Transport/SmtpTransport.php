<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Postal.
 *
 * (c) Lonnie Ezell <lonnieje@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Postal\Transport;

use Myth\Postal\Address;
use Myth\Postal\Email;
use Myth\Postal\Exceptions\SmtpException;
use Myth\Postal\MessageRenderer;
use Myth\Postal\SendResult;

/**
 * Delivers a message by speaking SMTP over a mockable socket seam. Supports
 * AUTH login/plain/xoauth2, STARTTLS and implicit SSL/TLS, persistent
 * keep-alive across sends in one request, and DSN delivery notifications.
 */
final class SmtpTransport implements TransportInterface
{
    private const CRLF = "\r\n";

    private readonly SmtpSocket $socket;
    private readonly string $host;
    private readonly int $port;
    private readonly int $timeout;
    private readonly string $helo;
    private readonly string $encryption;
    private readonly ?string $username;
    private readonly ?string $password;
    private readonly ?string $authType;
    private readonly bool $keepAlive;

    /**
     * DSN options: ['notify' => 'SUCCESS,FAILURE,DELAY', 'ret' => 'FULL'|'HDRS', 'envid' => '...'].
     *
     * @var array<string, string>
     */
    private readonly array $dsn;

    /**
     * Whether the live connection has completed its handshake (and auth) and is
     * ready to accept a transaction. Drives keep-alive reuse.
     */
    private bool $ready = false;

    /**
     * EHLO capability keywords advertised by the server, upper-cased.
     *
     * @var list<string>
     */
    private array $capabilities = [];

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings = [], ?SmtpSocket $socket = null)
    {
        $hostname = gethostname();

        $this->host    = (string) ($settings['host'] ?? 'localhost');
        $this->port    = (int) ($settings['port'] ?? 25);
        $this->timeout = (int) ($settings['timeout'] ?? 30);
        $this->helo    = isset($settings['helo'])
            ? (string) $settings['helo']
            : ($hostname !== false ? $hostname : 'localhost');
        $this->encryption = strtolower((string) ($settings['encryption'] ?? ''));
        $this->username   = isset($settings['username']) ? (string) $settings['username'] : null;
        $this->password   = isset($settings['password']) ? (string) $settings['password'] : null;
        $this->authType   = isset($settings['authType']) ? strtolower((string) $settings['authType']) : null;
        $this->keepAlive  = (bool) ($settings['keepAlive'] ?? false);

        /** @var array<string, string> $dsn */
        $dsn          = is_array($settings['dsn'] ?? null) ? $settings['dsn'] : [];
        $this->dsn    = $dsn;
        $this->socket = $socket ?? new StreamSocket();
    }

    public function send(Email $email): SendResult
    {
        $renderer = new MessageRenderer();
        $message  = $renderer->render($email);

        try {
            $this->ensureReady();
            $this->transaction($email, $message);

            $messageId = $renderer->headers()['Message-ID'] ?? null;

            if (! $this->keepAlive) {
                $this->quit();
            }

            return SendResult::ok($messageId);
        } catch (SmtpException $e) {
            // Drop the connection so the next attempt starts clean.
            $this->reset();

            return SendResult::fail($e->getMessage());
        }
    }

    public function ping(): bool
    {
        try {
            $this->ensureReady();
            $this->quit();

            return true;
        } catch (SmtpException) {
            $this->reset();

            return false;
        }
    }

    /**
     * Opens and prepares the connection when needed. On a kept-alive connection
     * that is already prepared, only a RSET is issued to start a fresh
     * transaction.
     */
    private function ensureReady(): void
    {
        if ($this->ready && $this->socket->isConnected()) {
            try {
                $this->command('RSET', 250);

                return;
            } catch (SmtpException) {
                // The kept-alive connection went stale (idle timeout, peer
                // close); drop it and fall through to open a fresh one.
                $this->reset();
            }
        }

        // Port 465 is implicit TLS by default (RFC 8314); an explicit 'ssl' forces
        // it on any port. 'tls' (STARTTLS) connects in the clear and upgrades below.
        $implicitTls = $this->encryption === 'ssl'
            || ($this->encryption === '' && $this->port === 465);

        $address = $implicitTls ? 'ssl://' . $this->host : $this->host;
        $this->socket->connect($address, $this->port, $this->timeout);
        $this->expect('greeting', 220);

        $this->ehlo();

        if ($this->encryption === 'tls') {
            $this->startTls();
            $this->ehlo();
        }

        $this->authenticate();

        $this->ready = true;
    }

    /**
     * Runs one mail transaction: MAIL FROM, RCPT TO for every recipient, then
     * DATA with the rendered message.
     */
    private function transaction(Email $email, string $message): void
    {
        $from = $email->returnPath ?? ($email->from instanceof Address ? $email->from->email : '');
        $this->command('MAIL FROM:<' . $from . '>' . $this->mailParams(), 250);

        foreach ($this->recipients($email) as $recipient) {
            $this->command('RCPT TO:<' . $recipient . '>' . $this->rcptParams($recipient), 250, 251);
        }

        $this->command('DATA', 354);
        $this->socket->write($this->prepareData($message) . self::CRLF . '.' . self::CRLF);
        $this->expect('end of DATA', 250);
    }

    /**
     * Sends EHLO and records the advertised capability keywords.
     */
    private function ehlo(): void
    {
        $reply              = $this->command('EHLO ' . $this->helo, 250);
        $this->capabilities = [];

        foreach (explode("\n", $reply) as $line) {
            $line = trim($line);
            // Drop the "250" / "250-" prefix, keep the keyword.
            $keyword = strtoupper(trim(substr($line, 4)));

            if ($keyword !== '') {
                $this->capabilities[] = $keyword;
            }
        }
    }

    /**
     * Performs the STARTTLS upgrade on the live connection.
     */
    private function startTls(): void
    {
        $this->command('STARTTLS', 220);

        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT
            | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
            | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;

        if (! $this->socket->enableCrypto(true, $method)) {
            throw SmtpException::forUnexpectedReply('STARTTLS', 'TLS negotiation failed');
        }
    }

    /**
     * Authenticates when an auth type and credentials are configured.
     */
    private function authenticate(): void
    {
        if ($this->authType === null) {
            return;
        }

        switch ($this->authType) {
            case 'login':
                $this->command('AUTH LOGIN', 334);
                $this->secret(base64_encode((string) $this->username), 'AUTH LOGIN (username)', 334);
                $this->secret(base64_encode((string) $this->password), 'AUTH LOGIN (password)', 235);
                break;

            case 'plain':
                $credentials = base64_encode("\0" . $this->username . "\0" . $this->password);
                $this->secret('AUTH PLAIN ' . $credentials, 'AUTH PLAIN', 235);
                break;

            case 'xoauth2':
                $token = base64_encode(
                    'user=' . $this->username . "\1auth=Bearer " . $this->password . "\1\1",
                );
                $this->secret('AUTH XOAUTH2 ' . $token, 'AUTH XOAUTH2', 235);
                break;

            default:
                throw SmtpException::forUnsupportedAuth($this->authType);
        }
    }

    /**
     * Closes the conversation politely.
     */
    private function quit(): void
    {
        try {
            $this->command('QUIT', 221);
        } catch (SmtpException) {
            // The message is already accepted by this point; a noisy or missing
            // QUIT reply must not turn a delivered message into a failed send.
        } finally {
            $this->reset();
        }
    }

    /**
     * Tears down the connection and clears per-connection state.
     */
    private function reset(): void
    {
        $this->socket->close();
        $this->ready        = false;
        $this->capabilities = [];
    }

    /**
     * Writes a command line and asserts the reply opens with an accepted code.
     */
    private function command(string $command, int ...$accepted): string
    {
        return $this->writeLine($command, $command, ...$accepted);
    }

    /**
     * Sends a command whose payload is sensitive (an auth credential), reporting
     * errors against a safe label so credentials never reach an exception
     * message or log.
     */
    private function secret(string $command, string $label, int ...$accepted): string
    {
        return $this->writeLine($command, $label, ...$accepted);
    }

    /**
     * Writes a single command line and asserts the reply, reporting any failure
     * against $label (which may redact the wire payload).
     */
    private function writeLine(string $command, string $label, int ...$accepted): string
    {
        // A command is a single line. Strip any embedded CR/LF so an attacker
        // cannot smuggle extra SMTP commands through an address, HELO name, or
        // DSN parameter (SMTP command injection).
        $command = str_replace(["\r", "\n"], '', $command);

        $this->socket->write($command . self::CRLF);

        return $this->expect($label, ...$accepted);
    }

    /**
     * Reads a (possibly multiline) reply and asserts its code is accepted.
     */
    private function expect(string $context, int ...$accepted): string
    {
        $reply = $this->readReply();
        $code  = (int) substr($reply, 0, 3);

        if (! in_array($code, $accepted, true)) {
            throw SmtpException::forUnexpectedReply($context, trim($reply));
        }

        return $reply;
    }

    /**
     * Reads a complete SMTP reply, following multiline continuations ("250-").
     */
    private function readReply(): string
    {
        $reply = '';

        do {
            $line = $this->socket->readLine();
            $reply .= $line;
            $line = rtrim($line, "\r\n");
            // A hyphen in the 4th position marks a continuation line.
            $continued = strlen($line) >= 4 && $line[3] === '-';
        } while ($continued);

        return $reply;
    }

    /**
     * The full envelope recipient list: To, Cc and Bcc.
     *
     * @return list<string>
     */
    private function recipients(Email $email): array
    {
        $emails = static fn (Address $a): string => $a->email;

        return [
            ...array_map($emails, $email->to),
            ...array_map($emails, $email->cc),
            ...array_map($emails, $email->bcc),
        ];
    }

    /**
     * ESMTP parameters appended to MAIL FROM for DSN, when supported.
     */
    private function mailParams(): string
    {
        if ($this->dsn === [] || ! $this->supports('DSN')) {
            return '';
        }

        $params = '';

        if (($this->dsn['ret'] ?? '') !== '') {
            $params .= ' RET=' . strtoupper($this->dsn['ret']);
        }

        if (($this->dsn['envid'] ?? '') !== '') {
            $params .= ' ENVID=' . $this->dsn['envid'];
        }

        return $params;
    }

    /**
     * ESMTP parameters appended to RCPT TO for DSN, when supported.
     */
    private function rcptParams(string $recipient): string
    {
        if ($this->dsn === [] || ! $this->supports('DSN') || ($this->dsn['notify'] ?? '') === '') {
            return '';
        }

        return ' NOTIFY=' . strtoupper($this->dsn['notify'])
            . ' ORCPT=rfc822;' . $recipient;
    }

    /**
     * Returns true when the server advertised the given EHLO keyword.
     */
    private function supports(string $keyword): bool
    {
        foreach ($this->capabilities as $capability) {
            if ($capability === $keyword || str_starts_with($capability, $keyword . ' ')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalises line endings to CRLF and dot-stuffs lines that begin with a dot
     * so they cannot be read as the end-of-data terminator.
     */
    private function prepareData(string $message): string
    {
        $message = (string) preg_replace('/\r\n|\r|\n/', self::CRLF, $message);

        return (string) preg_replace('/^\./m', '..', $message);
    }
}
