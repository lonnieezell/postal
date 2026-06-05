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
use Myth\Postal\SendResult;

/**
 * @internal
 */
final class SendResultTest extends CIUnitTestCase
{
    public function testOkIsSuccessful(): void
    {
        $result = SendResult::ok('msg-123', ['provider' => 'raw']);

        $this->assertTrue($result->success);
        $this->assertFalse($result->cancelled);
        $this->assertSame('msg-123', $result->messageId);
        $this->assertNull($result->error);
        $this->assertSame(['provider' => 'raw'], $result->raw);
    }

    public function testOkDefaultsToNullMessageIdAndRaw(): void
    {
        $result = SendResult::ok();

        $this->assertTrue($result->success);
        $this->assertNull($result->messageId);
        $this->assertNull($result->raw);
    }

    public function testFailCarriesError(): void
    {
        $result = SendResult::fail('connection refused', ['code' => 500]);

        $this->assertFalse($result->success);
        $this->assertFalse($result->cancelled);
        $this->assertSame('connection refused', $result->error);
        $this->assertNull($result->messageId);
        $this->assertSame(['code' => 500], $result->raw);
    }

    public function testCancelledIsNotSuccessful(): void
    {
        $result = SendResult::cancelled();

        $this->assertFalse($result->success);
        $this->assertTrue($result->cancelled);
        $this->assertNull($result->error);
        $this->assertNull($result->messageId);
    }

    public function testCancelledWithReasonExposesReasonAsError(): void
    {
        $result = SendResult::cancelled('All recipients are suppressed');

        $this->assertFalse($result->success);
        $this->assertTrue($result->cancelled);
        $this->assertSame('All recipients are suppressed', $result->error);
    }
}
