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

namespace Myth\Postal;

use Config\Email as LegacyConfig;
use Myth\Postal\Transport\MailTransport;
use Myth\Postal\Transport\SendmailTransport;
use Myth\Postal\Transport\SmtpTransport;
use Myth\Postal\Transport\TransportInterface;

/**
 * A drop-in replacement for CodeIgniter 4's Email service, exposing the legacy
 * fluent API on top of the new Email builder and Mailer. Existing application
 * code keeps working unchanged; legacy flat Config\Email keys are honoured so no
 * configuration changes are required.
 */
class LegacyEmailAdapter
{
    use LegacyEmailCompat;

    private readonly LegacyConfig $config;

    /**
     * The message under construction.
     */
    private Email $email;

    /**
     * Raw body, routed to the HTML or text part by $mailType at send time.
     */
    private string $body = '';

    /**
     * The plain-text alternative for an HTML message.
     */
    private string $altMessage = '';

    /**
     * 'text' or 'html'.
     */
    private string $mailType;

    /**
     * 'mail', 'sendmail' or 'smtp'.
     */
    private string $protocol;

    private bool $wordWrap;
    private int $wrapChars;
    private bool $BCCBatchMode;
    private int $BCCBatchSize;

    /**
     * Recorded attachments, translated to native Attachments at send time.
     *
     * @var list<array{name: array{0: string, 1: string|null}, disposition: string, mime: string, cid: string|null}>
     */
    private array $attachments = [];

    /**
     * Debugger lines (errors and notices) for printDebugger().
     *
     * @var list<string>
     */
    private array $debugMessage = [];

    /**
     * Set when an address failed validation; forces send() to return false.
     */
    private bool $validationFailed = false;

    private ?Email $lastEmail       = null;
    private ?SendResult $lastResult = null;

    /**
     * @param TransportInterface|null $transportOverride optional override used for testing;
     *                                                   when null the transport is resolved from the protocol
     */
    public function __construct(?LegacyConfig $config = null, private readonly ?TransportInterface $transportOverride = null)
    {
        // Clone so initialize()'s flat-key merge never mutates the shared config.
        $this->config = clone ($config ?? config(LegacyConfig::class));

        $this->protocol = in_array($this->config->protocol, ['mail', 'sendmail', 'smtp'], true)
            ? $this->config->protocol
            : 'mail';
        $this->mailType     = $this->config->mailType === 'html' ? 'html' : 'text';
        $this->wordWrap     = $this->config->wordWrap;
        $this->wrapChars    = $this->config->wrapChars;
        $this->BCCBatchMode = $this->config->BCCBatchMode;
        $this->BCCBatchSize = $this->config->BCCBatchSize;

        $this->email = $this->freshEmail();
    }

    /**
     * Merges per-call configuration, mirroring the legacy initialize(). Only the
     * keys an application is likely to pass are mapped.
     *
     * @param array<string, mixed> $config
     */
    public function initialize(array $config): static
    {
        if (isset($config['protocol'])) {
            $this->setProtocol((string) $config['protocol']);
        }
        if (isset($config['mailType'])) {
            $this->setMailType((string) $config['mailType']);
        }
        if (isset($config['wordWrap'])) {
            $this->wordWrap = (bool) $config['wordWrap'];
        }
        if (isset($config['wrapChars'])) {
            $this->wrapChars = (int) $config['wrapChars'];
        }
        if (isset($config['priority'])) {
            $this->setPriority((int) $config['priority']);
        }
        if (isset($config['BCCBatchMode'])) {
            $this->BCCBatchMode = (bool) $config['BCCBatchMode'];
        }
        if (isset($config['BCCBatchSize'])) {
            $this->BCCBatchSize = (int) $config['BCCBatchSize'];
        }

        // Fold remaining flat keys (SMTPHost, mailPath, …) onto the config so
        // transport resolution sees the updated values.
        foreach ($config as $key => $value) {
            if (property_exists($this->config, $key)) {
                $this->config->{$key} = $value;
            }
        }

        return $this;
    }

    /**
     * Resets the message content. Configuration (protocol, mail type, wrap, BCC
     * batch settings) is preserved, matching the legacy clear().
     */
    public function clear(bool $clearAttachments = false): static
    {
        $this->email            = $this->freshEmail();
        $this->body             = '';
        $this->altMessage       = '';
        $this->debugMessage     = [];
        $this->validationFailed = false;

        if ($clearAttachments) {
            $this->attachments = [];
        }

        return $this;
    }

    /**
     * Sends the message through a Mailer, returning the legacy boolean result.
     * Invalid addresses, a missing From, missing recipients, or an unreadable
     * attachment cause a false return surfaced via printDebugger() — never an
     * exception.
     */
    public function send(bool $autoClear = true): bool
    {
        if ($this->email->from === null && $this->config->fromEmail !== '') {
            $this->setFrom($this->config->fromEmail, $this->config->fromName);
        }

        if ($this->validationFailed) {
            return false;
        }

        if (! $this->applyAttachments()) {
            return false;
        }

        if ($this->email->from === null) {
            $this->fail('You did not specify a From address.');

            return false;
        }

        if ($this->email->to === [] && $this->email->cc === [] && $this->email->bcc === []) {
            $this->fail('You must include recipients: To, Cc, or Bcc.');

            return false;
        }

        // CI4 parity: Reply-To defaults to From when none was set.
        if ($this->email->replyTo === null) {
            $this->email->replyTo = $this->email->from;
        }

        $this->prepareEmail();
        $mailer = $this->buildMailer();

        $ok = ($this->BCCBatchMode && count($this->email->bcc) > $this->BCCBatchSize)
            ? $this->sendBatches($mailer)
            : $this->dispatch($mailer, $this->email);

        if ($ok && $autoClear) {
            $this->clear();
        }

        return $ok;
    }

    /**
     * Sends the blind recipients in BCCBatchSize chunks; the result is the
     * logical AND of every batch. Legacy-only — the new Mailer has no batch mode.
     */
    public function batchBCCSend(): bool
    {
        $this->prepareEmail();

        return $this->sendBatches($this->buildMailer());
    }

    /**
     * Renders the current message and returns a debugger string: any recorded
     * errors followed by the selected parts (headers, subject, body). Compatible
     * with the legacy output, though not byte-identical.
     *
     * @param list<string>|string $include
     */
    public function printDebugger(array|string $include = ['headers', 'subject', 'body']): string
    {
        $this->prepareEmail();

        $renderer  = new MessageRenderer();
        $mime      = $renderer->render($this->email);
        $parts     = explode("\r\n\r\n", $mime, 2);
        $headerStr = $parts[0];
        $body      = $parts[1] ?? '';
        $subject   = $renderer->headers()['Subject'] ?? '';

        if (! is_array($include)) {
            $include = [$include];
        }

        $raw = '';
        if (in_array('headers', $include, true)) {
            $raw .= htmlspecialchars($headerStr) . "\n";
        }
        if (in_array('subject', $include, true)) {
            $raw .= htmlspecialchars($subject) . "\n";
        }
        if (in_array('body', $include, true)) {
            $raw .= htmlspecialchars($body);
        }

        $messages = '';

        foreach ($this->debugMessage as $line) {
            // Lines may embed user-supplied addresses; escape so the debugger
            // output cannot be used as a reflected-XSS vector.
            $messages .= htmlspecialchars($line) . '<br>';
        }

        return $messages . ($raw === '' ? '' : '<pre>' . $raw . '</pre>');
    }

    /**
     * The SendResult of the most recent dispatch, or null before any send.
     */
    public function lastResult(): ?SendResult
    {
        return $this->lastResult;
    }

    /**
     * The Email of the most recent dispatch, or null before any send.
     */
    public function lastEmail(): ?Email
    {
        return $this->lastEmail;
    }

    /**
     * Builds the transport for the current protocol from the legacy flat config,
     * unless a transport override was injected for testing.
     */
    protected function resolveTransport(): TransportInterface
    {
        if ($this->transportOverride !== null) {
            return $this->transportOverride;
        }

        return match ($this->protocol) {
            'smtp'     => new SmtpTransport($this->smtpSettings()),
            'sendmail' => new SendmailTransport(['path' => $this->config->mailPath]),
            default    => new MailTransport(),
        };
    }

    /**
     * A new Email seeded with the configured default priority.
     */
    private function freshEmail(): Email
    {
        $email           = new Email();
        $email->priority = $this->config->priority;

        return $email;
    }

    /** Applies the deferred body, mail type and wrap settings onto the Email. */
    /**
     * Translates the recorded legacy attachments into native Attachment objects
     * on the underlying Email. Path-based files and inline images (those given a
     * CID via setAttachmentCID()) are delivered; an unreadable path — including
     * an unsupported in-memory buffer — fails the send gracefully rather than
     * throwing at render time. Returns false when an attachment cannot be read.
     */
    private function applyAttachments(): bool
    {
        $this->email->attachments = [];

        foreach ($this->attachments as $attachment) {
            $path    = (string) $attachment['name'][0];
            $newname = $attachment['name'][1];
            $name    = ($newname === null || $newname === '') ? null : (string) $newname;
            $mime    = $attachment['mime'] === '' ? null : $attachment['mime'];

            if (! is_file($path)) {
                $this->fail('Unable to read attachment: ' . $path);

                return false;
            }

            $this->email->attachments[] = $attachment['cid'] !== null
                ? Attachment::embed($path, $attachment['cid'], $name, $mime)
                : Attachment::fromPath($path, $name, $mime);
        }

        return true;
    }

    private function prepareEmail(): void
    {
        if ($this->mailType === 'html') {
            $this->email->htmlBody = $this->body;
            $this->email->textBody = $this->altMessage !== '' ? $this->altMessage : null;
        } else {
            $this->email->htmlBody = null;
            $this->email->textBody = $this->body;
        }

        $this->email->wordWrap  = $this->wordWrap;
        $this->email->wrapChars = $this->wrapChars;
    }

    private function buildMailer(): Mailer
    {
        // Participate in Postal's event pipeline so adapter sends behave like
        // any other send (a listener may cancel via email.sending).
        return new Mailer($this->resolveTransport(), fireEvents: true);
    }

    /**
     * Dispatches one message, recording the result and any error for the debugger.
     */
    private function dispatch(Mailer $mailer, Email $email): bool
    {
        $result           = $mailer->send($email);
        $this->lastEmail  = $email;
        $this->lastResult = $result;

        if (! $result->success && $result->error !== null) {
            $this->fail($result->error);
        }

        return $result->success;
    }

    /**
     * Sends the blind recipients in chunks, ANDing the per-batch results.
     */
    private function sendBatches(Mailer $mailer): bool
    {
        $all  = $this->email->bcc;
        $size = max(1, $this->BCCBatchSize);
        $ok   = true;

        foreach (array_chunk($all, $size) as $chunk) {
            $this->email->bcc = $chunk;
            $ok               = $this->dispatch($mailer, $this->email) && $ok;
        }

        $this->email->bcc = $all;

        return $ok;
    }

    /**
     * The SMTP transport settings mapped from the legacy flat config keys.
     *
     * @return array<string, mixed>
     */
    private function smtpSettings(): array
    {
        $settings = [
            'host'       => $this->config->SMTPHost,
            'port'       => $this->config->SMTPPort,
            'timeout'    => $this->config->SMTPTimeout,
            'encryption' => $this->config->SMTPCrypto,
            'username'   => $this->config->SMTPUser,
            'password'   => $this->config->SMTPPass,
            'keepAlive'  => $this->config->SMTPKeepAlive,
        ];

        if ($this->config->SMTPUser !== '' && $this->config->SMTPPass !== '') {
            $settings['authType'] = $this->config->SMTPAuthMethod;
        }

        if ($this->config->DSN) {
            $settings['dsn'] = ['notify' => 'SUCCESS,FAILURE,DELAY', 'ret' => 'FULL'];
        }

        return $settings;
    }

    /**
     * Records a debugger line.
     */
    private function fail(string $message): void
    {
        $this->debugMessage[] = $message;
    }
}
