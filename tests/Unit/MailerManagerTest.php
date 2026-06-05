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

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Config\Email as EmailConfig;
use Myth\Postal\Email;
use Myth\Postal\Exceptions\PackageException;
use Myth\Postal\Mailer;
use Myth\Postal\MailerManager;
use Psr\Log\AbstractLogger;
use Stringable;

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

    public function testConfigShipsLogMailer(): void
    {
        $config = new EmailConfig();

        $this->assertArrayHasKey('log', $config->mailers);
        $this->assertArrayHasKey('log', $config->transports);
    }

    public function testConfigShipsSmtpTransport(): void
    {
        $this->assertArrayHasKey('smtp', (new EmailConfig())->transports);
    }

    public function testResolvesSmtpMailerFromSettings(): void
    {
        $config          = new EmailConfig();
        $config->mailers = [
            'smtp' => [
                'transport' => 'smtp',
                'host'      => 'smtp.example.com',
                'port'      => 587,
            ],
        ];

        // Resolution must build the transport from its settings without throwing
        // (no connection is opened until send()).
        $this->assertInstanceOf(Mailer::class, (new MailerManager($config))->mailer('smtp'));
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

    public function testSendingHtmlViaLogMailerWritesMimeToLogger(): void
    {
        $logger = new class () extends AbstractLogger {
            public string $message = '';

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->message = (string) $message;
            }
        };
        Services::injectMock('logger', $logger);

        $manager = new MailerManager(new EmailConfig());

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject('Welcome')
            ->html('<p>Hello there</p>');

        $result = $manager->mailer('log')->send($email);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Subject: Welcome', $logger->message);
        $this->assertStringContainsString('Content-Type: multipart/alternative;', $logger->message);
        $this->assertStringContainsString('<p>Hello there</p>', $logger->message);

        Services::reset();
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
