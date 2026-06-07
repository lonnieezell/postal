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

namespace Tests\Support;

use Myth\Postal\Email;
use Myth\Postal\SendResult;
use Myth\Postal\Transport\TransportInterface;

/**
 * A transport that always reports failure, used to drive failover fall-through.
 */
final class AlwaysFailsTransport implements TransportInterface
{
    /**
     * @param array<string, mixed> $settings ignored; matches the uniform transport constructor
     */
    public function __construct(array $settings = [])
    {
    }

    public function send(Email $email): SendResult
    {
        return SendResult::fail('always fails');
    }

    public function ping(): bool
    {
        return false;
    }
}
