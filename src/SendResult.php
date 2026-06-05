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
 * The outcome of a send attempt returned by every transport.
 */
final readonly class SendResult
{
    private function __construct(
        public bool $success,
        public ?string $messageId = null,
        public ?string $error = null,
        public mixed $raw = null,
        public bool $cancelled = false,
    ) {
    }

    /**
     * A successful send, optionally carrying the provider message ID and raw response.
     */
    public static function ok(?string $messageId = null, mixed $raw = null): self
    {
        return new self(success: true, messageId: $messageId, raw: $raw);
    }

    /**
     * A failed send carrying the error message and optional raw response.
     */
    public static function fail(string $error, mixed $raw = null): self
    {
        return new self(success: false, error: $error, raw: $raw);
    }

    /**
     * A send cancelled before dispatch (e.g. by an event listener or suppression).
     */
    public static function cancelled(?string $reason = null): self
    {
        return new self(success: false, cancelled: true, error: $reason);
    }
}
