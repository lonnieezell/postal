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
final class SendmailTransport implements TransportInterface
{
    private readonly string $path;
    private readonly SendmailProcess $process;

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
        $message  = $renderer->render($email);

        if (! $this->process->open($this->path . ' -oi' . $this->fromFlag($email) . ' -t')) {
            return SendResult::fail('Could not open the sendmail process.');
        }

        $this->process->write($message);
        $status = $this->process->close();

        if ($status !== 0) {
            return SendResult::fail('Sendmail exited with status ' . $status . '.');
        }

        return SendResult::ok($renderer->headers()['Message-ID'] ?? null);
    }

    public function ping(): bool
    {
        return is_executable($this->path);
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
}
