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
use Myth\Postal\MessageRenderer;
use Myth\Postal\SendResult;

/**
 * Delivers a message through PHP's native mail() function. The rendered MIME is
 * split so the recipients and subject are passed as their own arguments (mail()
 * builds those headers itself) and the remaining headers and body follow.
 */
final readonly class MailTransport implements TransportInterface
{
    private MailFunction $mail;

    /**
     * The mail transport reads no settings; $settings is accepted only so the
     * MailerManager can construct every transport with the same call shape.
     *
     * @param array<string, mixed> $settings
     *
     * @phpstan-ignore constructor.unusedParameter
     */
    public function __construct(array $settings = [], ?MailFunction $mail = null)
    {
        $this->mail = $mail ?? new NativeMailFunction();
    }

    public function send(Email $email): SendResult
    {
        $renderer = new MessageRenderer();
        $body     = $this->bodyOf($renderer->render($email));
        $headers  = $renderer->headers();

        $sent = $this->mail->send(
            $this->recipients($email),
            $headers['Subject'] ?? '',
            $body,
            $this->additionalHeaders($headers, $email),
            $this->params($email),
        );

        if (! $sent) {
            return SendResult::fail('The mail() function rejected the message.');
        }

        return SendResult::ok($headers['Message-ID'] ?? null);
    }

    public function ping(): bool
    {
        return function_exists('mail');
    }

    /**
     * The comma-separated To recipients. mail() turns this into the To header,
     * so the rendered To header is dropped from the additional headers below.
     */
    private function recipients(Email $email): string
    {
        $emails = array_map(static fn (Address $a): string => $a->email, $email->to);

        return str_replace(["\r", "\n"], '', implode(', ', $emails));
    }

    /**
     * The rendered headers rebuilt as a CRLF-joined block, dropping To and
     * Subject which mail() supplies from its own arguments. A Bcc header is
     * appended when there are blind recipients: the renderer omits Bcc (it is
     * envelope-only for SMTP), so the transport adds it here for the MTA to
     * deliver to and then strip.
     *
     * @param array<string, string> $headers
     */
    private function additionalHeaders(array $headers, Email $email): string
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), ['to', 'subject'], true)) {
                continue;
            }

            $lines[] = $name . ': ' . $value;
        }

        if ($email->bcc !== []) {
            $list    = implode(', ', array_map(static fn (Address $a): string => $a->email, $email->bcc));
            $lines[] = 'Bcc: ' . str_replace(["\r", "\n"], '', $list);
        }

        return implode("\r\n", $lines);
    }

    /**
     * The "-f envelope-sender" argument for mail(), or an empty string when no
     * usable sender is configured. The address is validated and shell-escaped so
     * it cannot inject extra command-line arguments.
     */
    private function params(Email $email): string
    {
        $from = $email->returnPath ?? ($email->from instanceof Address ? $email->from->email : '');

        if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        return '-f ' . escapeshellarg($from);
    }

    /**
     * Splits the rendered message at the first blank line, returning the body.
     */
    private function bodyOf(string $message): string
    {
        $parts = explode("\r\n\r\n", $message, 2);

        return $parts[1] ?? '';
    }
}
