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

namespace Tests\Unit;

use Closure;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Email;
use Myth\Postal\Mailer;
use Myth\Postal\SendResult;
use Myth\Postal\Transport\NullTransport;
use Myth\Postal\Transport\TransportInterface;

/**
 * @internal
 */
final class MailerTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (['email.composing', 'email.sending', 'email.sent', 'email.failed'] as $event) {
            Events::removeAllListeners($event);
        }
    }

    /**
     * A transport that invokes the given recorder with 'transport' when it
     * runs, so the firing order relative to event listeners can be asserted.
     */
    private function recordingTransport(Closure $record): TransportInterface
    {
        return new class ($record) implements TransportInterface {
            public function __construct(private readonly Closure $record)
            {
            }

            public function send(Email $email): SendResult
            {
                ($this->record)('transport');

                return SendResult::ok('msg-1');
            }

            public function ping(): bool
            {
                return true;
            }
        };
    }

    public function testSendDelegatesToTransport(): void
    {
        $email = (new Email())->from('me@example.com')->to('you@example.com');

        $result = (new Mailer(new NullTransport()))->send($email);

        $this->assertTrue($result->success);
    }

    public function testSendClonesEmailBeforeDispatch(): void
    {
        $email = (new Email())->from('me@example.com')->to('you@example.com');

        $transport = new class () implements TransportInterface {
            public ?Email $received = null;

            public function send(Email $email): SendResult
            {
                $this->received = $email;

                return SendResult::ok();
            }

            public function ping(): bool
            {
                return true;
            }
        };

        (new Mailer($transport))->send($email);

        $this->assertNotSame($email, $transport->received);
        $this->assertSame($email->subject, $transport->received->subject);
    }

    public function testSendingListenerReturningFalseCancels(): void
    {
        Events::on('email.sending', static fn (): bool => false);

        $log    = [];
        $record = static function (string $step) use (&$log): void {
            $log[] = $step;
        };
        $transport = $this->recordingTransport($record);
        $email     = (new Email())->from('me@example.com')->to('you@example.com');

        $result = (new Mailer($transport))->send($email);

        $this->assertTrue($result->cancelled);
        $this->assertFalse($result->success);
        $this->assertNotContains('transport', $log);
    }

    public function testFiresComposingThenSendingThenTransportThenSent(): void
    {
        $log    = [];
        $record = static function (string $step) use (&$log): void {
            $log[] = $step;
        };

        Events::on('email.composing', static function () use ($record): void {
            $record('composing');
        });
        Events::on('email.sending', static function () use ($record): void {
            $record('sending');
        });
        Events::on('email.sent', static function () use ($record): void {
            $record('sent');
        });

        $transport = $this->recordingTransport($record);
        $email     = (new Email())->from('me@example.com')->to('you@example.com');

        (new Mailer($transport))->send($email);

        $this->assertSame(['composing', 'sending', 'transport', 'sent'], $log);
    }

    public function testSentEventReceivesMessageAndResult(): void
    {
        $captured = null;

        Events::on('email.sent', static function (Email $message, ?SendResult $result = null) use (&$captured): void {
            $captured = [$message, $result];
        });

        $transport = $this->recordingTransport(static function (string $step): void {});
        $email     = (new Email())->from('me@example.com')->to('you@example.com')->subject('Hi');

        (new Mailer($transport))->send($email);

        $this->assertNotNull($captured);
        $this->assertSame('Hi', $captured[0]->subject);
        $this->assertTrue($captured[1]->success);
        $this->assertSame('msg-1', $captured[1]->messageId);
    }

    public function testFiresFailedWithResultWhenTransportFails(): void
    {
        $captured = null;

        Events::on('email.failed', static function (Email $message, ?SendResult $result = null) use (&$captured): void {
            $captured = $result;
        });
        Events::on('email.sent', static function () use (&$captured): void {
            $captured = 'sent-should-not-fire';
        });

        $transport = new class () implements TransportInterface {
            public function send(Email $email): SendResult
            {
                return SendResult::fail('boom');
            }

            public function ping(): bool
            {
                return true;
            }
        };

        $email = (new Email())->from('me@example.com')->to('you@example.com');

        $result = (new Mailer($transport))->send($email);

        $this->assertFalse($result->success);
        $this->assertInstanceOf(SendResult::class, $captured);
        $this->assertSame('boom', $captured->error);
    }

    public function testFireEventsFalseSuppressesAllEventsAndStillSends(): void
    {
        $fired = [];

        foreach (['email.composing', 'email.sending', 'email.sent', 'email.failed'] as $event) {
            Events::on($event, static function () use ($event, &$fired): bool {
                $fired[] = $event;

                // A cancelling sending listener must be ignored when events are off.
                return false;
            });
        }

        $log    = [];
        $record = static function (string $step) use (&$log): void {
            $log[] = $step;
        };
        $transport = $this->recordingTransport($record);
        $email     = (new Email())->from('me@example.com')->to('you@example.com');

        $result = (new Mailer($transport, fireEvents: false))->send($email);

        $this->assertSame([], $fired);
        $this->assertContains('transport', $log);
        $this->assertTrue($result->success);
        $this->assertFalse($result->cancelled);
    }
}
