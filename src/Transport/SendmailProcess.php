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

/**
 * The process seam the sendmail transport writes through. Abstracting the pipe
 * lets the transport be unit-tested with a scripted double while the shipped
 * PopenProcess spawns a real sendmail binary.
 */
interface SendmailProcess
{
    /**
     * Opens the process for writing. Returns false when it could not be started.
     */
    public function open(string $command): bool;

    /**
     * Writes raw bytes to the process's stdin.
     */
    public function write(string $data): void;

    /**
     * Closes the pipe and returns the process exit status (0 on success).
     */
    public function close(): int;
}
