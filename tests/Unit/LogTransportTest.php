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
use Myth\Postal\Transport\LogTransport;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * @internal
 */
final class LogTransportTest extends CIUnitTestCase
{
    public function testSendLogsRenderedMimeAndReturnsOk(): void
    {
        $logger = $this->spyLogger();

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject('Greetings')
            ->html('<p>Hello</p>');

        $result = (new LogTransport($logger, 'debug'))->send($email);

        $this->assertTrue($result->success);
        $this->assertFalse($result->cancelled);
        $this->assertSame('debug', $logger->level);
        $this->assertStringContainsString('Subject: Greetings', (string) $logger->message);
        $this->assertStringContainsString('multipart/alternative', (string) $logger->message);
        $this->assertStringContainsString('<p>Hello</p>', (string) $logger->message);
    }

    public function testSendHonoursConfiguredLevel(): void
    {
        $logger = $this->spyLogger();

        (new LogTransport($logger, 'info'))->send((new Email())->from('me@example.com')->to('you@example.com')->text('Hi'));

        $this->assertSame('info', $logger->level);
    }

    public function testPingReturnsTrue(): void
    {
        $this->assertTrue((new LogTransport($this->spyLogger()))->ping());
    }

    /**
     * A PSR-3 logger that records the most recent level and message.
     */
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
