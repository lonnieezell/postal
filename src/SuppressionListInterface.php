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
 * Determines whether a recipient address should be excluded from a send.
 *
 * Bind an implementation in Config\Email::$suppressionList to have the
 * Mailer automatically remove suppressed recipients before dispatch.
 */
interface SuppressionListInterface
{
    /**
     * Returns true when the recipient should be removed from the send.
     */
    public function isSuppressed(Address $recipient): bool;
}
