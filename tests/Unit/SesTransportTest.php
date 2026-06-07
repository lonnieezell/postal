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

use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\SesV2\Exception\SesV2Exception;
use Aws\SesV2\SesV2Client;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Email;
use Myth\Postal\Transport\SesTransport;
use Psr\Http\Message\RequestInterface;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * @internal
 */
final class SesTransportTest extends CIUnitTestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $captured = [];

    public function testSendsSimpleEmailAndReturnsMessageId(): void
    {
        $transport = $this->transport($this->mock());

        $result = $transport->send(
            (new Email())
                ->from('me@example.com', 'Me')
                ->to('you@example.com')
                ->subject('Greetings')
                ->html('<p>Hello</p>')
                ->text('Hello'),
        );

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('amazon-ses-message-id', $result->messageId);

        $this->assertSame('"Me" <me@example.com>', $this->captured['FromEmailAddress']);
        $this->assertSame(['you@example.com'], $this->captured['Destination']['ToAddresses']);
        $this->assertSame('Greetings', $this->captured['Content']['Simple']['Subject']['Data']);
        $this->assertSame('<p>Hello</p>', $this->captured['Content']['Simple']['Body']['Html']['Data']);
        $this->assertSame('Hello', $this->captured['Content']['Simple']['Body']['Text']['Data']);
        $this->assertArrayNotHasKey('Raw', $this->captured['Content']);
    }

    public function testEncodesNonAsciiDisplayNamesInSimpleMode(): void
    {
        $this->transport($this->mock())->send(
            (new Email())->from('me@example.com', 'José')->to('you@example.com')->subject('Hi')->text('Hi'),
        );

        $from = $this->captured['FromEmailAddress'];
        // The raw multibyte name must not leak into the header field; it is
        // RFC 2047-encoded and the address remains intact.
        $this->assertStringNotContainsString('José', (string) $from);
        $this->assertStringContainsString('=?UTF-8?', (string) $from);
        $this->assertStringContainsString('<me@example.com>', (string) $from);
    }

    public function testMapsCcBccAndReplyTo(): void
    {
        $this->transport($this->mock())->send(
            $this->message()
                ->cc('cc@example.com')
                ->bcc('bcc@example.com')
                ->replyTo('reply@example.com'),
        );

        $this->assertSame(['cc@example.com'], $this->captured['Destination']['CcAddresses']);
        $this->assertSame(['bcc@example.com'], $this->captured['Destination']['BccAddresses']);
        $this->assertSame(['reply@example.com'], $this->captured['ReplyToAddresses']);
    }

    public function testUsesRawContentWhenAttachmentsPresent(): void
    {
        $this->transport($this->mock())->send(
            $this->message()->attachData('the-bytes', 'report.txt', 'text/plain'),
        );

        $this->assertArrayNotHasKey('Simple', $this->captured['Content']);
        $raw = $this->captured['Content']['Raw']['Data'];
        $this->assertStringContainsString('Subject: Hi', (string) $raw);
        $this->assertStringContainsString('report.txt', (string) $raw);
    }

    public function testHtmlWithoutTextUsesRawSoATextFallbackIsGenerated(): void
    {
        // The renderer turns HTML-only into multipart/alternative with a
        // generated text part; Simple content cannot, so HTML-only must go raw.
        $this->transport($this->mock())->send(
            (new Email())->from('me@example.com')->to('you@example.com')->subject('Hi')->html('<p>Hello</p>'),
        );

        $this->assertArrayNotHasKey('Simple', $this->captured['Content']);
        $raw = $this->captured['Content']['Raw']['Data'];
        $this->assertStringContainsString('multipart/alternative', (string) $raw);
        $this->assertStringContainsString('text/plain', (string) $raw);
    }

    public function testHtmlWithExplicitTextStaysSimple(): void
    {
        $this->transport($this->mock())->send(
            (new Email())->from('me@example.com')->to('you@example.com')->subject('Hi')->html('<p>Hi</p>')->text('Hi'),
        );

        $this->assertArrayHasKey('Simple', $this->captured['Content']);
        $this->assertArrayNotHasKey('Raw', $this->captured['Content']);
    }

    public function testForceRawSettingSelectsRawWithoutAttachments(): void
    {
        $this->transport($this->mock(), ['forceRaw' => true])->send($this->message());

        $this->assertArrayHasKey('Raw', $this->captured['Content']);
        $this->assertArrayNotHasKey('Simple', $this->captured['Content']);
    }

    public function testMapsMetadataToEmailTags(): void
    {
        $this->transport($this->mock())->send(
            $this->message()->metadata('campaign', 'spring')->metadata('tier', 'gold'),
        );

        $this->assertSame([
            ['Name' => 'campaign', 'Value' => 'spring'],
            ['Name' => 'tier', 'Value' => 'gold'],
        ], $this->captured['EmailTags']);
    }

    public function testDropsAndLogsInvalidEmailTags(): void
    {
        $logger = $this->spyLogger();

        $this->transport($this->mock(), [], $logger)->send(
            $this->message()->metadata('ok_tag', 'fine')->metadata('bad key', 'value'),
        );

        $this->assertSame([['Name' => 'ok_tag', 'Value' => 'fine']], $this->captured['EmailTags']);
        $this->assertSame('debug', $logger->level);
        $this->assertStringContainsString('bad key', (string) $logger->message);
    }

    public function testOmitsEmailTagsWhenNoneSurvive(): void
    {
        $this->transport($this->mock())->send($this->message()->metadata('bad key', 'value'));

        $this->assertArrayNotHasKey('EmailTags', $this->captured);
    }

    public function testMapsConfigurationSetFromSettings(): void
    {
        $this->transport($this->mock(), ['configurationSet' => 'my-config-set'])->send($this->message());

        $this->assertSame('my-config-set', $this->captured['ConfigurationSetName']);
    }

    public function testAwsErrorBecomesFailWithoutThrowing(): void
    {
        $mock = new MockHandler();
        $mock->append(static fn (CommandInterface $cmd): SesV2Exception => new SesV2Exception(
            'Rejected',
            $cmd,
            ['message' => 'Email address is not verified.'],
        ));

        $result = $this->transport($mock)->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not verified', (string) $result->error);
    }

    public function testPingReturnsTrue(): void
    {
        $this->assertTrue($this->transport($this->mock())->ping());
    }

    /**
     * A mock handler that records the SendEmail parameters and returns a fixed
     * message id.
     */
    private function mock(): MockHandler
    {
        $mock = new MockHandler();
        $mock->append(function (CommandInterface $cmd, RequestInterface $req): Result {
            $this->captured = $cmd->toArray();

            return new Result(['MessageId' => 'amazon-ses-message-id']);
        });

        return $mock;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function transport(MockHandler $handler, array $settings = [], ?object $logger = null): SesTransport
    {
        $client = new SesV2Client([
            'region'      => 'us-east-1',
            'version'     => 'latest',
            'credentials' => ['key' => 'AKIATEST', 'secret' => 'secret'],
            'handler'     => $handler,
        ]);

        return new SesTransport($settings, $client, $logger);
    }

    private function message(): Email
    {
        return (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject('Hi')
            ->text('Hello there');
    }

    private function spyLogger(): object
    {
        return new class () extends AbstractLogger {
            public string $level   = '';
            public string $message = '';

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->level   = (string) $level;
                $this->message = (string) $message;
            }
        };
    }
}
