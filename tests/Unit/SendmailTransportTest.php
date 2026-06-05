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
use Myth\Postal\Transport\SendmailTransport;
use Tests\Support\FakeSendmailProcess;

/**
 * @internal
 */
final class SendmailTransportTest extends CIUnitTestCase
{
    public function testWritesRenderedMessageAndReturnsOk(): void
    {
        $process = new FakeSendmailProcess();

        $result = $this->transport($process)->send($this->message());

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertTrue($process->closed);
        $this->assertStringContainsString('Subject: Hi', $process->written);
        $this->assertStringContainsString('Hello there', $process->written);
        // The sendmail invocation reads recipients from the message with -t.
        $this->assertStringContainsString(' -t', (string) $process->command);
    }

    public function testHonorsConfiguredPath(): void
    {
        $process = new FakeSendmailProcess();

        $this->transport($process, ['path' => '/custom/sendmail'])->send($this->message());

        $this->assertStringStartsWith('/custom/sendmail', (string) $process->command);
    }

    public function testDefaultsToSystemSendmailPath(): void
    {
        $process = new FakeSendmailProcess();

        $this->transport($process)->send($this->message());

        $this->assertStringStartsWith('/usr/sbin/sendmail', (string) $process->command);
    }

    public function testUsesEnvelopeSenderAsFromFlag(): void
    {
        $process = new FakeSendmailProcess();

        $this->transport($process)->send($this->message()->returnPath('bounce@example.com'));

        $this->assertStringContainsString('-f ' . escapeshellarg('bounce@example.com'), (string) $process->command);
    }

    public function testFallsBackToFromAddressForEnvelopeSender(): void
    {
        $process = new FakeSendmailProcess();

        $this->transport($process)->send($this->message());

        $this->assertStringContainsString('-f ' . escapeshellarg('me@example.com'), (string) $process->command);
    }

    public function testFailsWhenProcessCannotOpen(): void
    {
        $process = new FakeSendmailProcess(openResult: false);

        $result = $this->transport($process)->send($this->message());

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
        $this->assertSame('', $process->written);
    }

    public function testFailsWhenExitStatusIsNonZero(): void
    {
        $process = new FakeSendmailProcess(exitStatus: 1);

        $result = $this->transport($process)->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('1', (string) $result->error);
    }

    public function testReturnsMessageId(): void
    {
        $process = new FakeSendmailProcess();

        $result = $this->transport($process)->send($this->message());

        $this->assertNotNull($result->messageId);
        $this->assertStringContainsString('@example.com', $result->messageId);
    }

    public function testIncludesBccAsAHeaderSoMinusTDeliversIt(): void
    {
        $process = new FakeSendmailProcess();

        // The renderer omits Bcc; the transport must add it so sendmail -t
        // delivers to (and then strips) the blind recipients.
        $this->transport($process)->send(
            $this->message()->bcc(['hidden@example.com', 'shadow@example.com']),
        );

        $this->assertStringContainsString('Bcc: hidden@example.com, shadow@example.com', $process->written);
    }

    public function testOmitsBccHeaderWhenThereAreNoBlindRecipients(): void
    {
        $process = new FakeSendmailProcess();

        $this->transport($process)->send($this->message());

        $this->assertStringNotContainsString('Bcc:', $process->written);
    }

    public function testRejectsShellMetacharactersInEnvelopeSender(): void
    {
        $process = new FakeSendmailProcess();

        // A return-path carrying shell metacharacters must never reach the command.
        $this->transport($process)->send($this->message()->returnPath('x@example.com; rm -rf /'));

        $this->assertStringNotContainsString('rm -rf', (string) $process->command);
        $this->assertStringNotContainsString('-f ', (string) $process->command);
    }

    public function testPingIsTrueForExecutableBinary(): void
    {
        $this->assertTrue($this->transport(new FakeSendmailProcess(), ['path' => PHP_BINARY])->ping());
    }

    public function testPingIsFalseForMissingBinary(): void
    {
        $this->assertFalse($this->transport(new FakeSendmailProcess(), ['path' => '/no/such/binary'])->ping());
    }

    private function message(): Email
    {
        return (new Email())
            ->from('me@example.com', 'Me')
            ->to('you@example.com')
            ->subject('Hi')
            ->text('Hello there');
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function transport(FakeSendmailProcess $process, array $settings = []): SendmailTransport
    {
        return new SendmailTransport($settings, $process);
    }
}
