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

use Closure;
use Myth\Postal\Email;
use Myth\Postal\SendResult;
use PHPUnit\Framework\Assert;

/**
 * An in-memory test double that records every message instead of delivering it,
 * exposing content-matcher assertions so sends can be verified without a live
 * MTA or network access. Obtain one via Mailer::fake().
 */
final class FakeTransport implements TransportInterface
{
    /**
     * @var list<Email>
     */
    private array $sent = [];

    public function send(Email $email): SendResult
    {
        $this->sent[] = $email;

        return SendResult::ok('fake-' . count($this->sent));
    }

    public function ping(): bool
    {
        return true;
    }

    /**
     * Returns the recorded messages, optionally filtered to those matching the
     * given matcher. The matcher may be a content closure or a Mailable
     * class-string (matching messages tagged with that mailable).
     *
     * @param (Closure(Email): bool)|class-string|null $callback
     *
     * @return list<Email>
     */
    public function sent(Closure|string|null $callback = null): array
    {
        if ($callback === null) {
            return $this->sent;
        }

        return array_values(array_filter($this->sent, $this->matcher($callback)));
    }

    /**
     * Asserts at least one recorded message satisfies the matcher. Pass a
     * content closure, or a Mailable class-string with an optional closure to
     * further filter messages tagged with that mailable.
     *
     * @param (Closure(Email): bool)|class-string $callback
     * @param (Closure(Email): bool)|null         $filter
     */
    public function assertSent(Closure|string $callback, ?Closure $filter = null): void
    {
        $matched = $this->sent($callback);

        if ($filter !== null) {
            $matched = array_values(array_filter($matched, $filter));
        }

        Assert::assertNotEmpty(
            $matched,
            is_string($callback)
                ? "No [{$callback}] message matching the given expectations was sent."
                : 'The expected message was not sent.',
        );
    }

    /**
     * Asserts at least one recorded message was addressed to the given email,
     * in any of the To, Cc, or Bcc buckets.
     */
    public function assertSentTo(string $email): void
    {
        Assert::assertNotEmpty(
            $this->sent(fn (Email $message): bool => $this->hasRecipient($message, $email)),
            "No message was sent to [{$email}].",
        );
    }

    /**
     * Asserts no recorded message satisfies the content matcher.
     *
     * @param Closure(Email): bool $callback
     */
    public function assertNotSent(Closure $callback): void
    {
        Assert::assertCount(
            0,
            $this->sent($callback),
            'An unexpected message matching the matcher was sent.',
        );
    }

    /**
     * Asserts no message was recorded at all.
     */
    public function assertNothingSent(): void
    {
        Assert::assertCount(
            0,
            $this->sent,
            sprintf('Expected no messages to be sent, but %d were.', count($this->sent)),
        );
    }

    /**
     * Asserts exactly the given number of messages were recorded.
     */
    public function assertSentCount(int $count): void
    {
        Assert::assertCount(
            $count,
            $this->sent,
            sprintf('Expected %d message(s) to be sent, but %d were.', $count, count($this->sent)),
        );
    }

    /**
     * Resolves a matcher into a predicate. A closure is used as-is; a
     * class-string matches messages tagged with that Mailable class.
     *
     * @param (Closure(Email): bool)|class-string $callback
     *
     * @return Closure(Email): bool
     */
    private function matcher(Closure|string $callback): Closure
    {
        if ($callback instanceof Closure) {
            return $callback;
        }

        return static fn (Email $email): bool => $email->mailableClass === $callback;
    }

    /**
     * Returns true when the email is addressed to the given address in any of
     * the To, Cc, or Bcc buckets (case-insensitive).
     */
    private function hasRecipient(Email $email, string $address): bool
    {
        foreach ([...$email->to, ...$email->cc, ...$email->bcc] as $recipient) {
            if (strcasecmp($recipient->email, $address) === 0) {
                return true;
            }
        }

        return false;
    }
}
