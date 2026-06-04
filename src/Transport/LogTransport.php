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
use Myth\Postal\MessageRenderer;
use Myth\Postal\SendResult;
use Psr\Log\LoggerInterface;

/**
 * A transport that renders the message to MIME and writes it to a PSR-3 logger
 * instead of delivering it. Useful in development to inspect outgoing mail.
 */
final readonly class LogTransport implements TransportInterface
{
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null, private string $level = 'debug')
    {
        $this->logger = $logger ?? service('logger');
    }

    public function send(Email $email): SendResult
    {
        $this->logger->log($this->level, (new MessageRenderer())->render($email));

        return SendResult::ok();
    }

    public function ping(): bool
    {
        return true;
    }
}
