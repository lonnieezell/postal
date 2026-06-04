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
use Myth\Postal\Config\Email as EmailConfig;
use Myth\Postal\Email;
use Myth\Postal\Exceptions\PackageException;
use Myth\Postal\MailerManager;

/**
 * @internal
 */
final class MailerManagerTest extends CIUnitTestCase
{
    public function testConfigShipsNullMailerAsDefault(): void
    {
        $config = new EmailConfig();

        $this->assertSame('null', $config->default);
        $this->assertArrayHasKey('null', $config->mailers);
        $this->assertArrayHasKey('null', $config->transports);
    }

    public function testMailerInstancesAreCached(): void
    {
        $manager = new MailerManager(new EmailConfig());

        $this->assertSame($manager->mailer('null'), $manager->mailer('null'));
    }

    public function testSendUsesDefaultMailer(): void
    {
        $manager = new MailerManager(new EmailConfig());

        $email = (new Email())->from('me@example.com')->to('you@example.com');

        $this->assertTrue($manager->send($email)->success);
    }

    public function testUnknownMailerThrows(): void
    {
        $manager = new MailerManager(new EmailConfig());

        $this->expectException(PackageException::class);
        $manager->mailer('does-not-exist');
    }

    public function testUnknownTransportThrows(): void
    {
        $config          = new EmailConfig();
        $config->mailers = ['broken' => ['transport' => 'ghost']];
        $config->default = 'broken';

        $manager = new MailerManager($config);

        $this->expectException(PackageException::class);
        $manager->mailer();
    }
}
