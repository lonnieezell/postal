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
use Myth\Postal\Config\Services;
use Myth\Postal\Transport\FakeTransport;
use Myth\Postal\Transport\TransportInterface;

/**
 * Orchestrates a send: clones the message at the dispatch boundary, applies
 * suppression filtering and unsubscribe header injection, fires the email
 * lifecycle events, and hands the message to the bound transport.
 */
class Mailer
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly bool $fireEvents = true,
        private readonly ?SuppressionListInterface $suppressionList = null,
        private readonly ?UnsubscribeUrlInterface $unsubscribeUrl = null,
    ) {
    }

    /**
     * Swaps the bound mailer service for an in-memory double so tests can assert
     * on what would have been sent without touching a real transport. Every
     * mailer resolved from service('mailer') routes through the returned double.
     */
    public static function fake(): FakeTransport
    {
        $transport = new FakeTransport();
        $mailer    = new self($transport);

        Services::injectMock('mailer', new class ($mailer) extends MailerManager {
            public function __construct(private readonly Mailer $fakeMailer)
            {
            }

            public function mailer(?string $name = null): Mailer
            {
                return $this->fakeMailer;
            }
        });

        return $transport;
    }

    public function send(Email $email): SendResult
    {
        $message = clone $email;

        $this->emit('email.composing', $message);

        if ($this->suppressionList !== null) {
            $message->to  = $this->filterSuppressed($message->to);
            $message->cc  = $this->filterSuppressed($message->cc);
            $message->bcc = $this->filterSuppressed($message->bcc);

            if ($message->to === [] && $message->cc === [] && $message->bcc === []) {
                return SendResult::cancelled('All recipients are suppressed');
            }
        }

        $this->injectUnsubscribeHeaders($message);

        if ($this->emit('email.sending', $message) === false) {
            return SendResult::cancelled();
        }

        $result = $this->transport->send($message);

        $this->emit($result->success ? 'email.sent' : 'email.failed', $message, $result);

        return $result;
    }

    /**
     * Removes suppressed addresses from $bucket, emitting email.suppressed per removal.
     *
     * @param list<Address> $bucket
     *
     * @return list<Address>
     */
    private function filterSuppressed(array $bucket): array
    {
        $kept = [];

        foreach ($bucket as $address) {
            if ($this->suppressionList->isSuppressed($address)) {
                $this->emit('email.suppressed', $address);
            } else {
                $kept[] = $address;
            }
        }

        return $kept;
    }

    /**
     * Auto-injects List-Unsubscribe (and List-Unsubscribe-Post for one-click)
     * when a URL provider is bound, the message has exactly one To recipient,
     * and the header has not already been set explicitly.
     */
    private function injectUnsubscribeHeaders(Email $message): void
    {
        if ($this->unsubscribeUrl === null) {
            return;
        }

        if (count($message->to) !== 1) {
            return;
        }

        if (isset($message->headers['List-Unsubscribe'])) {
            return;
        }

        $url                                  = $this->unsubscribeUrl->urlFor($message->to[0]);
        $message->headers['List-Unsubscribe'] = '<' . $url . '>';

        if ($this->unsubscribeUrl->isOneClick()) {
            $message->headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }
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
