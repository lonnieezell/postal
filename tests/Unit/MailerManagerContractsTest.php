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
use Myth\Postal\Address;
use Myth\Postal\Config\Email as EmailConfig;
use Myth\Postal\Email;
use Myth\Postal\MailerManager;
use Myth\Postal\SendResult;
use Myth\Postal\SuppressionListInterface;
use Myth\Postal\Transport\TransportInterface;
use Myth\Postal\UnsubscribeUrlInterface;

/**
 * @internal
 */
final class MailerManagerContractsTest extends CIUnitTestCase
{
    public function testManagerInjectsSuppressionListFromConfig(): void
    {
        $config                  = new EmailConfig();
        $config->suppressionList = SuppressAllForTest::class;

        $manager = new MailerManager($config);
        $email   = (new Email())->from('me@example.com')->to('a@example.com');

        $result = $manager->send($email);

        $this->assertTrue($result->cancelled);
    }

    public function testManagerInjectsUnsubscribeUrlFromConfig(): void
    {
        $config                 = new EmailConfig();
        $config->unsubscribeUrl = UnsubscribeUrlForTest::class;
        $config->transports     = ['capturing' => CapturingTransportForTest::class];
        $config->mailers        = ['default' => ['transport' => 'capturing']];
        $config->default        = 'default';

        $manager = new MailerManager($config);
        $email   = (new Email())->from('me@example.com')->to('a@example.com');

        $manager->send($email);

        $this->assertSame('<https://example.com/unsub>', CapturingTransportForTest::$lastReceived->headers['List-Unsubscribe'] ?? null);

        CapturingTransportForTest::$lastReceived = null;
    }

    public function testNullContractsInConfigAreIgnored(): void
    {
        $config                  = new EmailConfig();
        $config->suppressionList = null;
        $config->unsubscribeUrl  = null;

        $manager = new MailerManager($config);
        $email   = (new Email())->from('me@example.com')->to('a@example.com');

        $this->assertTrue($manager->send($email)->success);
    }
}

final class SuppressAllForTest implements SuppressionListInterface
{
    public function isSuppressed(Address $recipient): bool
    {
        return true;
    }
}

final class UnsubscribeUrlForTest implements UnsubscribeUrlInterface
{
    public function urlFor(Address $recipient): string
    {
        return 'https://example.com/unsub';
    }

    public function isOneClick(): bool
    {
        return false;
    }
}

final class CapturingTransportForTest implements TransportInterface
{
    public static ?Email $lastReceived = null;

    public function send(Email $email): SendResult
    {
        self::$lastReceived = $email;

        return SendResult::ok();
    }

    public function ping(): bool
    {
        return true;
    }
}
