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

use CodeIgniter\Config\Services as FrameworkServices;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Config\Services as PostalServices;
use Myth\Postal\Email;
use Myth\Postal\LegacyEmailAdapter;

/**
 * @internal
 */
final class MailerServiceTest extends CIUnitTestCase
{
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

        $this->assertTrue($result->success);
        $this->assertFalse($result->cancelled);
    }

    public function testEmailServiceReturnsLegacyAdapter(): void
    {
        // CIUnitTestCase injects a MockEmail for the 'email' service in setUp;
        // drop it so discovery resolves this package's override (as it would in
        // a real application, where mockEmail() never runs).
        FrameworkServices::resetSingle('email');

        $this->assertInstanceOf(LegacyEmailAdapter::class, service('email'));
    }

    public function testEmailServiceIsShared(): void
    {
        FrameworkServices::resetSingle('email');

        $this->assertSame(service('email'), service('email'));
    }

    public function testEmailServiceNonSharedReturnsFreshAdapter(): void
    {
        $this->assertNotSame(
            PostalServices::email(null, false),
            PostalServices::email(null, false),
        );
    }
}
