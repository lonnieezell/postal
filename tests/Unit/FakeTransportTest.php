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

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Email;
use Myth\Postal\Mailer;
use Myth\Postal\Transport\FakeTransport;
use PHPUnit\Framework\AssertionFailedError;

/**
 * @internal
 */
final class FakeTransportTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Services::reset();
    }

    private function email(string $to = 'you@example.com', string $subject = 'Hi'): Email
    {
        return (new Email())->from('me@example.com')->to($to)->subject($subject);
    }

    public function testSendRecordsTheMessageAndReportsSuccess(): void
    {
        $fake = new FakeTransport();

        $result = $fake->send($this->email());

        $this->assertTrue($result->success);
        $this->assertCount(1, $fake->sent());
    }

    public function testSentReturnsTheRecordedEmails(): void
    {
        $fake = new FakeTransport();
        $fake->send($this->email(subject: 'One'));
        $fake->send($this->email(subject: 'Two'));

        $sent = $fake->sent();

        $this->assertCount(2, $sent);
        $this->assertSame('One', $sent[0]->subject);
        $this->assertSame('Two', $sent[1]->subject);
    }

    public function testSentWithCallbackFilters(): void
    {
        $fake = new FakeTransport();
        $fake->send($this->email(subject: 'Keep'));
        $fake->send($this->email(subject: 'Drop'));

        $matches = $fake->sent(static fn (Email $email): bool => $email->subject === 'Keep');

        $this->assertCount(1, $matches);
        $this->assertSame('Keep', $matches[0]->subject);
    }

    public function testAssertSentPassesWhenAMessageMatches(): void
    {
        $fake = new FakeTransport();
        $fake->send($this->email(subject: 'Welcome'));

        $fake->assertSent(static fn (Email $email): bool => $email->subject === 'Welcome');
    }

    public function testAssertSentFailsWhenNoMessageMatches(): void
    {
        $fake = new FakeTransport();
        $fake->send($this->email(subject: 'Welcome'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('The expected message was not sent.');

        $fake->assertSent(static fn (Email $email): bool => $email->subject === 'Nope');
    }

    public function testAssertSentToMatchesToCcAndBcc(): void
    {
        $fake = new FakeTransport();
        $fake->send((new Email())->from('me@example.com')->to('to@example.com'));
        $fake->send((new Email())->from('me@example.com')->cc('cc@example.com'));
        $fake->send((new Email())->from('me@example.com')->bcc('bcc@example.com'));

        $fake->assertSentTo('to@example.com');
        $fake->assertSentTo('cc@example.com');
        $fake->assertSentTo('bcc@example.com');
    }

    public function testAssertSentToFailsWithRecipientInMessage(): void
    {
        $fake = new FakeTransport();
        $fake->send($this->email('you@example.com'));

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('nobody@example.com');

        $fake->assertSentTo('nobody@example.com');
    }

    public function testAssertNotSentPassesWhenNoneMatch(): void
    {
        $fake = new FakeTransport();
        $fake->send($this->email(subject: 'Welcome'));

        $fake->assertNotSent(static fn (Email $email): bool => $email->subject === 'Other');
    }

    public function testAssertNotSentFailsWhenAMessageMatches(): void
    {
        $fake = new FakeTransport();
        $fake->send($this->email(subject: 'Welcome'));

        $this->expectException(AssertionFailedError::class);

        $fake->assertNotSent(static fn (Email $email): bool => $email->subject === 'Welcome');
    }

    public function testAssertNothingSentPassesWhenEmpty(): void
    {
        (new FakeTransport())->assertNothingSent();
    }

    public function testAssertNothingSentFailsAfterASend(): void
    {
        $fake = new FakeTransport();
        $fake->send($this->email());

        $this->expectException(AssertionFailedError::class);

        $fake->assertNothingSent();
    }

    public function testAssertSentCountMatches(): void
    {
        $fake = new FakeTransport();
        $fake->send($this->email());
        $fake->send($this->email());

        $fake->assertSentCount(2);
    }

    public function testAssertSentCountFailsWithExpectedCountInMessage(): void
    {
        $fake = new FakeTransport();
        $fake->send($this->email());

        $this->expectException(AssertionFailedError::class);

        $fake->assertSentCount(3);
    }

    public function testFakeReturnsTheRecordingDouble(): void
    {
        $fake = Mailer::fake();

        $this->assertInstanceOf(FakeTransport::class, $fake);
    }

    public function testFakeSwapsTheMailerServiceTransport(): void
    {
        $fake = Mailer::fake();

        $result = service('mailer')->send($this->email('routed@example.com'));

        $this->assertTrue($result->success);
        $fake->assertSentTo('routed@example.com');
    }

    public function testFakeRoutesNamedMailersThroughTheSameDouble(): void
    {
        $fake = Mailer::fake();

        service('mailer')->mailer('null')->send($this->email('named@example.com'));

        $fake->assertSentCount(1);
        $fake->assertSentTo('named@example.com');
    }
}
