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
 * Delivers a message by piping the rendered MIME to a sendmail binary. The
 * binary is invoked with -t, so it reads the recipients from the message
 * headers itself.
 */
final readonly class SendmailTransport implements RawMimeTransport
{
    private string $path;
    private SendmailProcess $process;

    /**
     * @param array<string, mixed> $settings accepts an optional "path" key
     */
    public function __construct(array $settings = [], ?SendmailProcess $process = null)
    {
        $this->path    = (string) ($settings['path'] ?? '/usr/sbin/sendmail');
        $this->process = $process ?? new PopenProcess();
    }

    public function send(Email $email): SendResult
    {
        $renderer = new MessageRenderer();
        $message  = $this->withBcc($email->rawMessage ?? $renderer->render($email), $email);

        if (! $this->process->open($this->path . ' -oi' . $this->fromFlag($email) . ' -t')) {
            return SendResult::fail('Could not open the sendmail process.');
        }

        $this->process->write($message);
        $status = $this->process->close();

        if ($status !== 0) {
            return SendResult::fail('Sendmail exited with status ' . $status . '.');
        }

        return SendResult::ok(
            $email->rawMessage !== null
                ? $this->messageIdFromRaw($message)
                : ($renderer->headers()['Message-ID'] ?? null),
        );
    }

    public function ping(): bool
    {
        return is_executable($this->path);
    }

    /**
     * Extracts the Message-ID header value from a pre-rendered raw message, or
     * null when it carries none.
     */
    private function messageIdFromRaw(string $message): ?string
    {
        if (preg_match('/^Message-ID:[ \t]*(.+?)[ \t]*\r?$/im', $message, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * The "-f envelope-sender" argument, or an empty string when no usable
     * sender is configured. The address is validated and shell-escaped so it
     * cannot inject extra command-line arguments.
     */
    private function fromFlag(Email $email): string
    {
        $from = $email->returnPath ?? ($email->from instanceof Address ? $email->from->email : '');

        if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        return ' -f ' . escapeshellarg($from);
    }

    /**
     * Prepends a Bcc header to the rendered message when there are blind
     * recipients. The renderer omits Bcc (it is envelope-only for SMTP), but
     * sendmail -t reads recipients from the headers, so the Bcc must be present;
     * sendmail delivers to and then strips it.
     */
    private function withBcc(string $message, Email $email): string
    {
        if ($email->bcc === []) {
            return $message;
        }

        $list = implode(', ', array_map(static fn (Address $a): string => $a->email, $email->bcc));

        return 'Bcc: ' . str_replace(["\r", "\n"], '', $list) . "\r\n" . $message;
    }
}
