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

namespace Myth\Postal\Exceptions;

/**
 * Raised for SMTP connection and protocol failures. The SMTP transport catches
 * these during a conversation and reports them as a failed SendResult.
 */
class SmtpException extends PackageException
{
    public static function forConnectionFailure(string $host, int $port, string $error): self
    {
        return new self("Could not connect to SMTP server {$host}:{$port}: {$error}");
    }

    public static function forBrokenPipe(string $error): self
    {
        return new self("Lost connection to SMTP server: {$error}");
    }

    public static function forUnexpectedReply(string $command, string $reply): self
    {
        return new self("Unexpected reply to \"{$command}\": {$reply}");
    }

    public static function forUnsupportedAuth(string $type): self
    {
        return new self("Unsupported SMTP auth type \"{$type}\".");
    }
}
