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

use Myth\Postal\Email;
use Myth\Postal\SendResult;

/**
 * The contract every delivery transport implements.
 */
interface TransportInterface
{
    /**
     * Delivers the message. MUST NOT throw on delivery failure — return
     * SendResult::fail() instead. MAY throw on config/connection errors.
     */
    public function send(Email $email): SendResult;

    /**
     * Returns true when the transport has a usable connection.
     */
    public function ping(): bool;
}
