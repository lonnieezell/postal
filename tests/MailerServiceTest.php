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

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Email;
use Myth\Postal\MailerManager;
use Myth\Postal\SendResult;

/**
 * @internal
 */
final class MailerServiceTest extends CIUnitTestCase
{
    public function testServiceReturnsMailerManager(): void
    {
        $this->assertInstanceOf(MailerManager::class, service('mailer'));
    }

    public function testServiceIsShared(): void
    {
        $this->assertSame(service('mailer'), service('mailer'));
    }

    public function testServiceMailerSendsViaNullTransport(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject('Hello');

        $result = service('mailer')->send($email);

        $this->assertInstanceOf(SendResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertFalse($result->cancelled);
    }
}
