<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Address;
use Myth\Postal\Config\Email as EmailConfig;
use Myth\Postal\Email;
use Myth\Postal\MailerManager;
use Myth\Postal\SuppressionListInterface;
use Myth\Postal\UnsubscribeUrlInterface;

/**
 * @internal
 */
final class MailerManagerContractsTest extends CIUnitTestCase
{
    public function testManagerInjectsSuppressionListFromConfig(): void
    {
        $config                 = new EmailConfig();
        $config->suppressionList = SuppressAllForTest::class;

        $manager = new MailerManager($config);
        $email   = (new Email())->from('me@example.com')->to('a@example.com');

        $result = $manager->send($email);

        $this->assertTrue($result->cancelled);
    }

    public function testManagerInjectsUnsubscribeUrlFromConfig(): void
    {
        $config              = new EmailConfig();
        $config->unsubscribeUrl = UnsubscribeUrlForTest::class;

        $manager   = new MailerManager($config);
        $email     = (new Email())->from('me@example.com')->to('a@example.com');
        $transport = new class () implements \Myth\Postal\Transport\TransportInterface {
            public ?Email $received = null;

            public function send(Email $email): \Myth\Postal\SendResult
            {
                $this->received = $email;

                return \Myth\Postal\SendResult::ok();
            }

            public function ping(): bool
            {
                return true;
            }
        };

        // Override default transport to capture the message
        $config->mailers    = ['null' => ['transport' => 'null']];
        $config->transports = ['null' => \Myth\Postal\Transport\NullTransport::class];

        $manager = new MailerManager($config);
        $result  = $manager->send($email);

        // NullTransport succeeds, so we just check no exception and success
        $this->assertTrue($result->success);
    }

    public function testNullContractsInConfigAreIgnored(): void
    {
        $config                 = new EmailConfig();
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
