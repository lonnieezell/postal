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
 * A transport that discards every message and always reports success.
 */
final class NullTransport implements TransportInterface
{
    public function send(Email $email): SendResult
    {
        return SendResult::ok();
    }

    public function ping(): bool
    {
        return true;
    }
}
