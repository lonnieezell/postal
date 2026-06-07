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
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\SendResult;
use Throwable;

/**
 * A composite transport that tries its child transports in order, returning the
 * first success and falling through to the next on failure. It reports failure
 * only when every child fails.
 */
final readonly class FailoverTransport implements TransportInterface
{
    /**
     * @param list<TransportInterface> $children the ordered child transports to try
     */
    public function __construct(private array $children = [])
    {
        if ($this->children === []) {
            throw PostalException::forEmptyFailover();
        }
    }

    public function send(Email $email): SendResult
    {
        $errors = [];

        foreach ($this->children as $child) {
            try {
                $result = $child->send($email);

                if ($result->success) {
                    return $result;
                }

                $errors[] = $result->error ?? 'unknown failure';
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        return SendResult::fail('All failover transports failed: ' . implode('; ', $errors));
    }

    public function ping(): bool
    {
        foreach ($this->children as $child) {
            try {
                if ($child->ping()) {
                    return true;
                }
            } catch (Throwable) {
                // A child that can't be reached doesn't make the composite unreachable.
            }
        }

        return false;
    }
}
