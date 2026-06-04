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

use Myth\Postal\Transport\TransportInterface;

/**
 * Orchestrates a send: clones the message at the dispatch boundary and hands
 * it to the bound transport.
 */
class Mailer
{
    public function __construct(private readonly TransportInterface $transport)
    {
    }

    public function send(Email $email): SendResult
    {
        return $this->transport->send(clone $email);
    }
}
