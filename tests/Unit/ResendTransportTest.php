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
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Transport\HttpResponse;
use Myth\Postal\Transport\ResendTransport;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Stringable;
use Tests\Support\FakeHttpClient;

/**
 * @internal
 */
final class ResendTransportTest extends CIUnitTestCase
{
    public function testSendsThroughResendApiAndReturnsMessageId(): void
    {
        $http = new FakeHttpClient(new HttpResponse(200, '{"id":"resend-message-id"}'));

        $result = (new ResendTransport(['key' => 're_test'], $http))->send(
            (new Email())
                ->from('me@example.com', 'Me')
                ->to('you@example.com')
                ->subject('Greetings')
                ->html('<p>Hello</p>')
                ->text('Hello'),
        );

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertSame('resend-message-id', $result->messageId);

        $this->assertSame('POST', $http->method);
        $this->assertSame('https://api.resend.com/emails', $http->url);
        $this->assertSame('Bearer re_test', $http->headers['Authorization']);
        $this->assertSame('application/json', $http->headers['Content-Type']);
        $this->assertSame([
            'from'    => '"Me" <me@example.com>',
            'to'      => ['you@example.com'],
            'subject' => 'Greetings',
            'html'    => '<p>Hello</p>',
            'text'    => 'Hello',
        ], $http->json());
    }

    public function testMapsCcBccReplyToAndCustomHeaders(): void
    {
        $http = new FakeHttpClient();

        $this->transport($http)->send(
            $this->message()
                ->cc('cc@example.com')
                ->bcc('bcc@example.com', 'Blind')
                ->replyTo('reply@example.com')
                ->header('List-Unsubscribe', '<https://example.com/u>'),
        );

        $json = $http->json();
        $this->assertSame(['cc@example.com'], $json['cc']);
        $this->assertSame(['"Blind" <bcc@example.com>'], $json['bcc']);
        $this->assertSame(['reply@example.com'], $json['reply_to']);
        $this->assertSame(['List-Unsubscribe' => '<https://example.com/u>'], $json['headers']);
    }

    public function testMapsMetadataToTags(): void
    {
        $http = new FakeHttpClient();

        $this->transport($http)->send($this->message()->metadata('campaign', 'spring')->metadata('tier', 'gold'));

        $this->assertSame([
            ['name' => 'campaign', 'value' => 'spring'],
            ['name' => 'tier', 'value' => 'gold'],
        ], $http->json()['tags']);
    }

    public function testDropsAndDebugLogsInvalidTags(): void
    {
        $http   = new FakeHttpClient();
        $logger = $this->spyLogger();

        $this->transport($http, logger: $logger)->send(
            $this->message()->metadata('ok_tag', 'fine')->metadata('bad key', 'secret value'),
        );

        $this->assertSame([['name' => 'ok_tag', 'value' => 'fine']], $http->json()['tags']);
        $this->assertSame(['debug'], array_column($logger->records, 'level'));
        $this->assertStringContainsString('bad key', $logger->records[0]['message']);
        // Only the key is logged; values may carry personal data.
        $this->assertStringNotContainsString('secret value', $logger->records[0]['message']);
    }

    public function testOmitsTagsWhenNoneSurvive(): void
    {
        $http = new FakeHttpClient();

        $this->transport($http)->send($this->message()->metadata('bad key', 'value'));

        $this->assertArrayNotHasKey('tags', $http->json());
    }

    public function testDropsAndDebugLogsReturnPathAndPriority(): void
    {
        $http   = new FakeHttpClient();
        $logger = $this->spyLogger();

        $result = $this->transport($http, logger: $logger)->send(
            $this->message()->returnPath('bounce@example.com')->priority(1),
        );

        $this->assertTrue($result->success);
        $this->assertSame(['debug', 'debug'], array_column($logger->records, 'level'));
        $this->assertStringContainsString('return path', $logger->records[0]['message']);
        $this->assertStringContainsString('priority', $logger->records[1]['message']);
        $this->assertStringNotContainsString('bounce@example.com', (string) $http->body);
    }

    public function testDefaultPriorityAndNoReturnPathLogNothing(): void
    {
        $logger = $this->spyLogger();

        $this->transport(new FakeHttpClient(), logger: $logger)->send($this->message());

        $this->assertSame([], $logger->records);
    }

    public function testErrorResponseBecomesFailWithResendMessage(): void
    {
        $http = new FakeHttpClient(new HttpResponse(
            403,
            '{"statusCode":403,"message":"The example.com domain is not verified.","name":"validation_error"}',
        ));

        $result = $this->transport($http)->send($this->message());

        $this->assertFalse($result->success);
        $this->assertSame('The example.com domain is not verified.', $result->error);
        $this->assertSame('validation_error', $result->raw['name']);
    }

    public function testNonJsonErrorResponseReportsTheStatus(): void
    {
        $result = $this->transport(new FakeHttpClient(new HttpResponse(502, '<html>Bad Gateway</html>')))
            ->send($this->message());

        $this->assertFalse($result->success);
        $this->assertSame('HTTP 502', $result->error);
    }

    public function testClientExceptionBecomesFailWithoutThrowing(): void
    {
        $failure = new PostalException('Could not connect to api.resend.com');

        $result = $this->transport(new FakeHttpClient($failure))->send($this->message());

        $this->assertFalse($result->success);
        $this->assertSame('Could not connect to api.resend.com', $result->error);
        $this->assertSame($failure, $result->raw);
    }

    public function testMapsAttachmentsAndInlineImages(): void
    {
        $http = new FakeHttpClient();

        $this->transport($http)->send(
            $this->message()
                ->html('<p><img src="cid:logo"></p>')
                ->attachData('the-bytes', 'report.txt', 'text/plain')
                ->embedImage(__FILE__, 'logo', 'logo.png', 'image/png'),
        );

        $this->assertSame([
            [
                'content'      => base64_encode('the-bytes'),
                'filename'     => 'report.txt',
                'content_type' => 'text/plain',
            ],
            [
                'content'      => base64_encode((string) file_get_contents(__FILE__)),
                'filename'     => 'logo.png',
                'content_type' => 'image/png',
                'content_id'   => 'logo',
            ],
        ], $http->json()['attachments']);
    }

    public function testAutoEmbedsDataUriImagesAsInlineAttachments(): void
    {
        $http = new FakeHttpClient();

        $this->transport($http)->send(
            $this->message()->html('<p><img src="data:image/png;base64,' . base64_encode('PNGDATA') . '"></p>'),
        );

        $json = $http->json();
        $this->assertCount(1, $json['attachments']);
        $this->assertSame(base64_encode('PNGDATA'), $json['attachments'][0]['content']);
        $this->assertStringContainsString('cid:' . $json['attachments'][0]['content_id'], (string) $json['html']);
        $this->assertStringNotContainsString('data:image', (string) $json['html']);
    }

    public function testMissingApiKeyThrows(): void
    {
        $this->expectException(PostalException::class);
        $this->expectExceptionMessage('"key"');

        new ResendTransport([], new FakeHttpClient());
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function transport(FakeHttpClient $http, array $settings = ['key' => 're_test'], ?LoggerInterface $logger = null): ResendTransport
    {
        return new ResendTransport($settings, $http, $logger);
    }

    private function message(): Email
    {
        return (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject('Hi')
            ->text('Hello there');
    }

    /**
     * @return AbstractLogger&object{records: list<array{level: string, message: string}>}
     */
    private function spyLogger(): AbstractLogger
    {
        return new class () extends AbstractLogger {
            /**
             * @var list<array{level: string, message: string}>
             */
            public array $records = [];

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
            }
        };
    }
}
