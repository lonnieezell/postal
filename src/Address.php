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

/**
 * An email address with an optional display name.
 */
final readonly class Address
{
    public function __construct(
        public string $email,
        public string $name = '',
    ) {
    }

    /**
     * Parses an address from a "Name <email>" or bare "email" string.
     */
    public static function fromString(string $address): self
    {
        if (preg_match('/^\s*(.*?)\s*<\s*(.+?)\s*>\s*$/', $address, $matches) === 1) {
            return new self($matches[2], trim($matches[1], " \"'"));
        }

        return new self(trim($address));
    }

    /**
     * Renders the address as "Name <email>", or just "email" when unnamed.
     */
    public function toString(): string
    {
        return $this->name === '' ? $this->email : "{$this->name} <{$this->email}>";
    }

    /**
     * Validates an addr-spec, converting an IDN domain to ASCII first when the
     * intl extension is available so internationalised domains pass too.
     */
    public static function isValid(string $email): bool
    {
        if (
            function_exists('idn_to_ascii')
            && defined('INTL_IDNA_VARIANT_UTS46')
            && ($atpos = strpos($email, '@')) !== false
        ) {
            $ascii = idn_to_ascii(substr($email, $atpos + 1), 0, INTL_IDNA_VARIANT_UTS46);

            if ($ascii !== false) {
                $email = substr($email, 0, $atpos + 1) . $ascii;
            }
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
