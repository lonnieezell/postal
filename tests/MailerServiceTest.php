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
}
