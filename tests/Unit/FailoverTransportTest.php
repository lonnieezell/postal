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

use ArrayObject;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Email;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\SendResult;
use Myth\Postal\Transport\FailoverTransport;
use Myth\Postal\Transport\TransportInterface;
use RuntimeException;
use Tests\Support\RecordingTransport;

/**
 * @internal
 */
final class FailoverTransportTest extends CIUnitTestCase
{
    private function email(): Email
    {
        return (new Email())->from('me@example.com')->to('you@example.com');
    }

    public function testReturnsFirstChildSuccess(): void
    {
        $failover = new FailoverTransport([
            new RecordingTransport(succeed: true),
            new RecordingTransport(succeed: true),
        ]);

        $this->assertTrue($failover->send($this->email())->success);
    }

    public function testAdvancesToNextChildOnFailure(): void
    {
        $primary   = new RecordingTransport(succeed: false, error: 'primary down');
        $secondary = new RecordingTransport(succeed: true);

        $failover = new FailoverTransport([$primary, $secondary]);

        $result = $failover->send($this->email());

        $this->assertTrue($result->success);
        // The secondary actually received the message after the primary failed.
        $this->assertCount(1, $secondary->sent);
        $this->assertCount(1, $primary->sent);
    }

    public function testStopsAtFirstSuccessfulChild(): void
    {
        $primary   = new RecordingTransport(succeed: true);
        $secondary = new RecordingTransport(succeed: true);

        (new FailoverTransport([$primary, $secondary]))->send($this->email());

        // The secondary is never tried once the primary succeeds.
        $this->assertCount(1, $primary->sent);
        $this->assertCount(0, $secondary->sent);
    }

    public function testTriesChildrenInConfiguredOrder(): void
    {
        /** @var ArrayObject<int, string> $calls */
        $calls = new ArrayObject();

        $first  = $this->recordingOrder('first', $calls, succeed: false);
        $second = $this->recordingOrder('second', $calls, succeed: false);
        $third  = $this->recordingOrder('third', $calls, succeed: true);

        (new FailoverTransport([$first, $second, $third]))->send($this->email());

        $this->assertSame(['first', 'second', 'third'], $calls->getArrayCopy());
    }

    public function testReturnsFailureWhenAllChildrenFail(): void
    {
        $failover = new FailoverTransport([
            new RecordingTransport(succeed: false, error: 'first failed'),
            new RecordingTransport(succeed: false, error: 'second failed'),
        ]);

        $result = $failover->send($this->email());

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
        $this->assertStringContainsString('first failed', $result->error);
        $this->assertStringContainsString('second failed', $result->error);
    }

    public function testTreatsThrowingChildAsFailureAndAdvances(): void
    {
        $throwing = new class () implements TransportInterface {
            public function send(Email $email): SendResult
            {
                throw new RuntimeException('connection refused');
            }

            public function ping(): bool
            {
                return false;
            }
        };

        $secondary = new RecordingTransport(succeed: true);

        $result = (new FailoverTransport([$throwing, $secondary]))->send($this->email());

        $this->assertTrue($result->success);
        $this->assertCount(1, $secondary->sent);
    }

    public function testThrownChildErrorIsReportedWhenAllFail(): void
    {
        $throwing = new class () implements TransportInterface {
            public function send(Email $email): SendResult
            {
                throw new RuntimeException('connection refused');
            }

            public function ping(): bool
            {
                return false;
            }
        };

        $result = (new FailoverTransport([$throwing]))->send($this->email());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('connection refused', (string) $result->error);
    }

    public function testEmptyChildrenThrows(): void
    {
        $this->expectException(PostalException::class);

        new FailoverTransport([]);
    }

    public function testPingTrueWhenAnyChildPings(): void
    {
        $down = new class () implements TransportInterface {
            public function send(Email $email): SendResult
            {
                return SendResult::fail('down');
            }

            public function ping(): bool
            {
                return false;
            }
        };

        $failover = new FailoverTransport([$down, new RecordingTransport()]);

        $this->assertTrue($failover->ping());
    }

    public function testPingFalseWhenNoChildPings(): void
    {
        $down = static fn (): TransportInterface => new class () implements TransportInterface {
            public function send(Email $email): SendResult
            {
                return SendResult::fail('down');
            }

            public function ping(): bool
            {
                return false;
            }
        };

        $failover = new FailoverTransport([$down(), $down()]);

        $this->assertFalse($failover->ping());
    }

    /**
     * A transport that appends its label to a shared call log so test order can
     * be asserted, then returns the seeded result.
     *
     * @param ArrayObject<int, string> $log
     */
    private function recordingOrder(string $label, ArrayObject $log, bool $succeed): TransportInterface
    {
        return new class ($label, $log, $succeed) implements TransportInterface {
            /**
             * @param ArrayObject<int, string> $log
             */
            public function __construct(
                private readonly string $label,
                private readonly ArrayObject $log,
                private readonly bool $succeed,
            ) {
            }

            public function send(Email $email): SendResult
            {
                $this->log[] = $this->label;

                return $this->succeed ? SendResult::ok() : SendResult::fail("{$this->label} failed");
            }

            public function ping(): bool
            {
                return true;
            }
        };
    }
}
