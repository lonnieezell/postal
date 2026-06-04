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
use Myth\Postal\Mailer;
use Myth\Postal\SendResult;
use Myth\Postal\Transport\NullTransport;
use Myth\Postal\Transport\TransportInterface;

/**
 * @internal
 */
final class MailerTest extends CIUnitTestCase
{
    public function testSendDelegatesToTransport(): void
    {
        $email = (new Email())->from('me@example.com')->to('you@example.com');

        $result = (new Mailer(new NullTransport()))->send($email);

        $this->assertTrue($result->success);
    }

    public function testSendClonesEmailBeforeDispatch(): void
    {
        $email = (new Email())->from('me@example.com')->to('you@example.com');

        $transport = new class () implements TransportInterface {
            public ?Email $received = null;

            public function send(Email $email): SendResult
            {
                $this->received = $email;

                return SendResult::ok();
            }

            public function ping(): bool
            {
                return true;
            }
        };

        (new Mailer($transport))->send($email);

        $this->assertNotSame($email, $transport->received);
        $this->assertSame($email->subject, $transport->received->subject);
    }
}
