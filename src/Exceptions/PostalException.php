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
        return new self("No mailer named \"{$name}\" is defined in Config\\Email.");
    }

    public static function forUnknownTransport(string $name): self
    {
        return new self("No transport named \"{$name}\" is mapped in Config\\Email::\$transports.");
    }

    public static function forMissingPackage(string $package, string $transport): self
    {
        return new self("The \"{$transport}\" transport requires the \"{$package}\" package. Run \"composer require {$package}\".");
    }
}
