<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Address;
use Myth\Postal\Email;
use Myth\Postal\Mailer;
use Myth\Postal\SendResult;
use Myth\Postal\SuppressionListInterface;
use Myth\Postal\Transport\NullTransport;
use Myth\Postal\Transport\TransportInterface;

/**
 * @internal
 */
final class MailerSuppressionTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        Events::removeAllListeners('email.suppressed');
    }

    private function suppressingAll(): SuppressionListInterface
    {
        return new class () implements SuppressionListInterface {
            public function isSuppressed(Address $recipient): bool
            {
                return true;
            }
        };
    }

    private function suppressingNone(): SuppressionListInterface
    {
        return new class () implements SuppressionListInterface {
            public function isSuppressed(Address $recipient): bool
            {
                return false;
            }
        };
    }

    private function suppressingAddress(string $email): SuppressionListInterface
    {
        return new class ($email) implements SuppressionListInterface {
            public function __construct(private readonly string $email)
            {
            }

            public function isSuppressed(Address $recipient): bool
            {
                return $recipient->email === $this->email;
            }
        };
    }

    private function capturingTransport(): TransportInterface
    {
        return new class () implements TransportInterface {
            public bool $called = false;

            public function send(Email $email): SendResult
            {
                $this->called = true;

                return SendResult::ok();
            }

            public function ping(): bool
            {
                return true;
            }
        };
    }

    public function testNoSuppressionListPassesAllRecipients(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('a@example.com')
            ->to('b@example.com');

        $result = (new Mailer(new NullTransport()))->send($email);

        $this->assertTrue($result->success);
    }

    public function testSuppressedToRecipientIsRemoved(): void
    {
        $transport = $this->capturingTransport();
        $email     = (new Email())
            ->from('me@example.com')
            ->to('keep@example.com')
            ->to('suppressed@example.com');

        $suppression = $this->suppressingAddress('suppressed@example.com');
        (new Mailer($transport, suppressionList: $suppression))->send($email);

        $this->assertTrue($transport->called);
    }

    public function testSuppressedCcRecipientIsRemoved(): void
    {
        $captured = null;

        Events::on('email.suppressed', static function (Address $address) use (&$captured): void {
            $captured = $address->email;
        });

        $email = (new Email())
            ->from('me@example.com')
            ->to('keep@example.com')
            ->cc('suppressed@example.com');

        $suppression = $this->suppressingAddress('suppressed@example.com');
        (new Mailer(new NullTransport(), suppressionList: $suppression))->send($email);

        $this->assertSame('suppressed@example.com', $captured);
    }

    public function testSuppressedBccRecipientIsRemoved(): void
    {
        $captured = null;

        Events::on('email.suppressed', static function (Address $address) use (&$captured): void {
            $captured = $address->email;
        });

        $email = (new Email())
            ->from('me@example.com')
            ->to('keep@example.com')
            ->bcc('suppressed@example.com');

        $suppression = $this->suppressingAddress('suppressed@example.com');
        (new Mailer(new NullTransport(), suppressionList: $suppression))->send($email);

        $this->assertSame('suppressed@example.com', $captured);
    }

    public function testEmailSuppressedEventFiredPerRemovedRecipient(): void
    {
        $captured = [];

        Events::on('email.suppressed', static function (Address $address) use (&$captured): void {
            $captured[] = $address->email;
        });

        $email = (new Email())
            ->from('me@example.com')
            ->to('keep@example.com')
            ->to('suppressed1@example.com')
            ->cc('suppressed2@example.com');

        $suppression = new class () implements SuppressionListInterface {
            public function isSuppressed(Address $recipient): bool
            {
                return str_starts_with($recipient->email, 'suppressed');
            }
        };

        (new Mailer(new NullTransport(), suppressionList: $suppression))->send($email);

        sort($captured);
        $this->assertSame(['suppressed1@example.com', 'suppressed2@example.com'], $captured);
    }

    public function testAllRecipientsSupressedReturnsCancelled(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('a@example.com')
            ->cc('b@example.com');

        $result = (new Mailer(new NullTransport(), suppressionList: $this->suppressingAll()))->send($email);

        $this->assertTrue($result->cancelled);
        $this->assertFalse($result->success);
    }

    public function testAllRecipientsSupressedDoesNotCallTransport(): void
    {
        $transport = $this->capturingTransport();

        $email = (new Email())
            ->from('me@example.com')
            ->to('a@example.com');

        (new Mailer($transport, suppressionList: $this->suppressingAll()))->send($email);

        $this->assertFalse($transport->called);
    }

    public function testSuppressionListNonePassesThroughNormally(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('a@example.com')
            ->cc('b@example.com')
            ->bcc('c@example.com');

        $result = (new Mailer(new NullTransport(), suppressionList: $this->suppressingNone()))->send($email);

        $this->assertTrue($result->success);
    }

    public function testSuppressedEventNotFiredWhenEventsDisabled(): void
    {
        $fired = false;

        Events::on('email.suppressed', static function () use (&$fired): void {
            $fired = true;
        });

        $email = (new Email())
            ->from('me@example.com')
            ->to('keep@example.com')
            ->to('suppressed@example.com');

        $suppression = $this->suppressingAddress('suppressed@example.com');
        (new Mailer(new NullTransport(), fireEvents: false, suppressionList: $suppression))->send($email);

        $this->assertFalse($fired);
    }
}
