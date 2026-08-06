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
 * Generates a per-recipient unsubscribe URL for automatic List-Unsubscribe injection.
 *
 * Bind an implementation in Config\Mailer::$unsubscribeUrl to have the Mailer
 * automatically inject a List-Unsubscribe header when the message has exactly
 * one To recipient and the header has not already been set explicitly.
 */
interface UnsubscribeUrlInterface
{
    /**
     * Returns the unsubscribe URL for the given recipient.
     */
    public function urlFor(Address $recipient): string;

    /**
     * Returns true to also emit a List-Unsubscribe-Post header (RFC 8058 one-click).
     */
    public function isOneClick(): bool;
}
