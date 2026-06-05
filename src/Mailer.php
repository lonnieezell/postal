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

use CodeIgniter\Events\Events;
use Myth\Postal\Transport\TransportInterface;

/**
 * Orchestrates a send: clones the message at the dispatch boundary, fires the
 * email lifecycle events around it, and hands it to the bound transport.
 */
class Mailer
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly bool $fireEvents = true,
    ) {
    }

    public function send(Email $email): SendResult
    {
        $message = clone $email;

        $this->emit('email.composing', $message);

        if ($this->emit('email.sending', $message) === false) {
            return SendResult::cancelled();
        }

        $result = $this->transport->send($message);

        $this->emit($result->success ? 'email.sent' : 'email.failed', $message, $result);

        return $result;
    }

    /**
     * Fires a lifecycle event when events are enabled. Returns the trigger
     * result (false when a listener cancelled), or true when events are off.
     */
    private function emit(string $event, mixed ...$arguments): bool
    {
        if (! $this->fireEvents) {
            return true;
        }

        return Events::trigger($event, ...$arguments);
    }
}
