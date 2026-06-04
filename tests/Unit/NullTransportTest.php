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
use Myth\Postal\Transport\NullTransport;

/**
 * @internal
 */
final class NullTransportTest extends CIUnitTestCase
{
    public function testSendReturnsSuccessfulResult(): void
    {
        $email = (new Email())->from('me@example.com')->to('you@example.com');

        $result = (new NullTransport())->send($email);

        $this->assertTrue($result->success);
        $this->assertFalse($result->cancelled);
    }

    public function testPingReturnsTrue(): void
    {
        $this->assertTrue((new NullTransport())->ping());
    }
}
