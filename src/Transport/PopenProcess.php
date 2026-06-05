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
 * The shipped SendmailProcess implementation: a real sendmail process opened
 * for writing with popen().
 */
final class PopenProcess implements SendmailProcess
{
    /**
     * @var resource|null
     */
    private $handle;

    public function open(string $command): bool
    {
        if (! function_exists('popen')) {
            return false;
        }

        $handle = @popen($command, 'w');

        if ($handle === false) {
            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function write(string $data): void
    {
        if ($this->handle !== null) {
            @fwrite($this->handle, $data);
        }
    }

    public function close(): int
    {
        if ($this->handle === null) {
            return -1;
        }

        $status       = pclose($this->handle);
        $this->handle = null;

        return $status;
    }
}
