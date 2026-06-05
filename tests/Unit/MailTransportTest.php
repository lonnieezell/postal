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

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Email;
use Myth\Postal\Transport\MailTransport;
use Tests\Support\FakeMailFunction;

/**
 * @internal
 */
final class MailTransportTest extends CIUnitTestCase
{
    public function testSendsViaMailAndReturnsOk(): void
    {
        $mail = new FakeMailFunction();

        $result = $this->transport($mail)->send($this->message());

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('you@example.com', $mail->to);
        $this->assertStringContainsString('Hello there', (string) $mail->message);
    }

    public function testPassesSubjectAsItsOwnArgument(): void
    {
        $mail = new FakeMailFunction();

        $this->transport($mail)->send($this->message());

        $this->assertSame('Hi', $mail->subject);
        // mail() builds the Subject header itself; it must not be in the header block.
        $this->assertStringNotContainsString('Subject:', (string) $mail->headers);
    }

    public function testPassesRecipientsAsTheFirstArgumentNotAHeader(): void
    {
        $mail = new FakeMailFunction();

        $this->transport($mail)->send($this->message());

        $this->assertSame('you@example.com', $mail->to);
        // mail() builds the To header itself; it must not be duplicated in the block.
        $this->assertStringNotContainsString('To:', (string) $mail->headers);
    }

    public function testSplitsHeadersFromBody(): void
    {
        $mail = new FakeMailFunction();

        $this->transport($mail)->send($this->message());

        // Structural headers live in the headers argument...
        $this->assertStringContainsString('From:', (string) $mail->headers);
        $this->assertStringContainsString('MIME-Version:', (string) $mail->headers);
        // ...and never bleed into the body argument.
        $this->assertStringNotContainsString('MIME-Version:', (string) $mail->message);
        $this->assertStringNotContainsString('From:', (string) $mail->message);
    }

    public function testJoinsMultipleRecipientsWithCommas(): void
    {
        $mail = new FakeMailFunction();

        $this->transport($mail)->send($this->message()->to(['a@example.com', 'b@example.com']));

        $this->assertSame('you@example.com, a@example.com, b@example.com', $mail->to);
    }

    public function testCcIsDeliveredViaTheHeaderBlock(): void
    {
        $mail = new FakeMailFunction();

        $this->transport($mail)->send($this->message()->cc('cc@example.com'));

        $this->assertStringContainsString('Cc: cc@example.com', (string) $mail->headers);
    }

    public function testUsesEnvelopeSenderAsTheFParam(): void
    {
        $mail = new FakeMailFunction();

        $this->transport($mail)->send($this->message()->returnPath('bounce@example.com'));

        $this->assertSame('-f ' . escapeshellarg('bounce@example.com'), $mail->params);
    }

    public function testFallsBackToFromAddressForTheFParam(): void
    {
        $mail = new FakeMailFunction();

        $this->transport($mail)->send($this->message());

        $this->assertSame('-f ' . escapeshellarg('me@example.com'), $mail->params);
    }

    public function testRejectsShellMetacharactersInTheFParam(): void
    {
        $mail = new FakeMailFunction();

        $this->transport($mail)->send($this->message()->returnPath('x@example.com; rm -rf /'));

        $this->assertSame('', $mail->params);
    }

    public function testFailureReturnsFailedResult(): void
    {
        $mail = new FakeMailFunction(result: false);

        $result = $this->transport($mail)->send($this->message());

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
    }

    public function testReturnsMessageId(): void
    {
        $mail = new FakeMailFunction();

        $result = $this->transport($mail)->send($this->message());

        $this->assertNotNull($result->messageId);
        $this->assertStringContainsString('@example.com', $result->messageId);
    }

    public function testDeliversBccViaHeaderWithoutLeakingItIntoTheRecipientArgument(): void
    {
        $mail = new FakeMailFunction();

        $this->transport($mail)->send(
            $this->message()->bcc(['hidden@example.com', 'shadow@example.com']),
        );

        // mail() hands the Bcc header to the MTA, which delivers then strips it.
        $this->assertStringContainsString('Bcc: hidden@example.com, shadow@example.com', (string) $mail->headers);
        // Blind recipients must never appear in the visible To argument.
        $this->assertSame('you@example.com', $mail->to);
    }

    public function testOmitsBccHeaderWhenThereAreNoBlindRecipients(): void
    {
        $mail = new FakeMailFunction();

        $this->transport($mail)->send($this->message());

        $this->assertStringNotContainsString('Bcc:', (string) $mail->headers);
    }

    private function message(): Email
    {
        return (new Email())
            ->from('me@example.com', 'Me')
            ->to('you@example.com')
            ->subject('Hi')
            ->text('Hello there');
    }

    private function transport(FakeMailFunction $mail): MailTransport
    {
        return new MailTransport([], $mail);
    }
}
