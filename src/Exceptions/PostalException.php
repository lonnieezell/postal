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

use RuntimeException;

class PostalException extends RuntimeException
{
    public static function forUnknownMailer(string $name): self
    {
        return new self("No mailer named \"{$name}\" is defined in Config\\Mailer.");
    }

    public static function forUnknownTransport(string $name): self
    {
        return new self("No transport named \"{$name}\" is mapped in Config\\Mailer::\$transports.");
    }

    public static function forMissingPackage(string $package, string $transport): self
    {
        return new self("The \"{$transport}\" transport requires the \"{$package}\" package. Run \"composer require {$package}\".");
    }

    public static function forEmptyFailover(): self
    {
        return new self('A failover mailer must list at least one child mailer under its "chain" key.');
    }

    public static function forInvalidDkimConfig(string $requirement): self
    {
        return new self("DKIM signing requires {$requirement} in the mailer's \"dkim\" config.");
    }

    public static function forDkimUnsupported(string $transport): self
    {
        return new self("The \"{$transport}\" transport cannot DKIM-sign: it does not deliver raw MIME (such providers sign server-side). Remove the \"dkim\" config from this mailer.");
    }

    public static function forInvalidAddress(string $address): self
    {
        return new self("Invalid email address: \"{$address}\".");
    }
}
