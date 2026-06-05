<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Address;
use Myth\Postal\Email;
use Myth\Postal\Mailer;
use Myth\Postal\SendResult;
use Myth\Postal\Transport\NullTransport;
use Myth\Postal\Transport\TransportInterface;
use Myth\Postal\UnsubscribeUrlInterface;

/**
 * @internal
 */
final class MailerUnsubscribeTest extends CIUnitTestCase
{
    private function capturingTransport(): TransportInterface
    {
        return new class () implements TransportInterface {
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
    }

    private function simpleUnsubscribe(bool $oneClick = false): UnsubscribeUrlInterface
    {
        return new class ($oneClick) implements UnsubscribeUrlInterface {
            public function __construct(private readonly bool $oneClick)
            {
            }

            public function urlFor(Address $recipient): string
            {
                return 'https://example.com/unsub?email=' . $recipient->email;
            }

            public function isOneClick(): bool
            {
                return $this->oneClick;
            }
        };
    }

    public function testNoUnsubscribeInterfaceDoesNotInjectHeader(): void
    {
        $transport = $this->capturingTransport();
        $email     = (new Email())->from('me@example.com')->to('a@example.com');

        (new Mailer($transport))->send($email);

        $this->assertArrayNotHasKey('List-Unsubscribe', $transport->received->headers);
    }

    public function testSingleRecipientInjectsListUnsubscribeHeader(): void
    {
        $transport = $this->capturingTransport();
        $email     = (new Email())->from('me@example.com')->to('a@example.com');

        (new Mailer($transport, unsubscribeUrl: $this->simpleUnsubscribe()))->send($email);

        $this->assertArrayHasKey('List-Unsubscribe', $transport->received->headers);
        $this->assertStringContainsString('a@example.com', $transport->received->headers['List-Unsubscribe']);
    }

    public function testMultipleRecipientsSkipsAutoInjection(): void
    {
        $transport = $this->capturingTransport();
        $email     = (new Email())
            ->from('me@example.com')
            ->to('a@example.com')
            ->to('b@example.com');

        (new Mailer($transport, unsubscribeUrl: $this->simpleUnsubscribe()))->send($email);

        $this->assertArrayNotHasKey('List-Unsubscribe', $transport->received->headers);
    }

    public function testExplicitListUnsubscribeHeaderWins(): void
    {
        $transport = $this->capturingTransport();
        $email     = (new Email())
            ->from('me@example.com')
            ->to('a@example.com')
            ->listUnsubscribe('https://explicit.example.com/unsub');

        (new Mailer($transport, unsubscribeUrl: $this->simpleUnsubscribe()))->send($email);

        $this->assertStringContainsString('explicit.example.com', $transport->received->headers['List-Unsubscribe']);
    }

    public function testOneClickInjectsBothHeaders(): void
    {
        $transport = $this->capturingTransport();
        $email     = (new Email())->from('me@example.com')->to('a@example.com');

        (new Mailer($transport, unsubscribeUrl: $this->simpleUnsubscribe(oneClick: true)))->send($email);

        $this->assertArrayHasKey('List-Unsubscribe', $transport->received->headers);
        $this->assertArrayHasKey('List-Unsubscribe-Post', $transport->received->headers);
        $this->assertSame('List-Unsubscribe=One-Click', $transport->received->headers['List-Unsubscribe-Post']);
    }

    public function testNonOneClickDoesNotInjectPostHeader(): void
    {
        $transport = $this->capturingTransport();
        $email     = (new Email())->from('me@example.com')->to('a@example.com');

        (new Mailer($transport, unsubscribeUrl: $this->simpleUnsubscribe(oneClick: false)))->send($email);

        $this->assertArrayHasKey('List-Unsubscribe', $transport->received->headers);
        $this->assertArrayNotHasKey('List-Unsubscribe-Post', $transport->received->headers);
    }

    public function testListUnsubscribeHeaderFormattedWithAngleBrackets(): void
    {
        $transport = $this->capturingTransport();
        $email     = (new Email())->from('me@example.com')->to('a@example.com');

        (new Mailer($transport, unsubscribeUrl: $this->simpleUnsubscribe()))->send($email);

        $header = $transport->received->headers['List-Unsubscribe'];
        $this->assertStringStartsWith('<', $header);
        $this->assertStringEndsWith('>', $header);
    }
}
