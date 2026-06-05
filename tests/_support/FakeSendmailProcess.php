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

namespace Tests\Support;

use Myth\Postal\Transport\SendmailProcess;

/**
 * A scripted SendmailProcess double for unit-testing the sendmail transport
 * without spawning a real process. It records the command it was opened with
 * and everything written to it, and returns canned open/exit results.
 */
final class FakeSendmailProcess implements SendmailProcess
{
    public ?string $command = null;
    public string $written  = '';
    public bool $closed     = false;

    public function __construct(
        private readonly bool $openResult = true,
        private readonly int $exitStatus = 0,
    ) {
    }

    public function open(string $command): bool
    {
        $this->command = $command;

        return $this->openResult;
    }

    public function write(string $data): void
    {
        $this->written .= $data;
    }

    public function close(): int
    {
        $this->closed = true;

        return $this->exitStatus;
    }
}
