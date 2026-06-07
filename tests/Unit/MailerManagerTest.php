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
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Config\Email as EmailConfig;
use Myth\Postal\Email;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\MailerManager;
use Myth\Postal\Transport\DkimSigningTransport;
use Myth\Postal\Transport\FailoverTransport;
use Psr\Log\AbstractLogger;
use Stringable;
use Tests\Support\AlwaysFailsTransport;

/**
 * @internal
 */
final class MailerManagerTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Events::removeAllListeners('email.sending');
    }

    public function testConfigEnablesEventsByDefault(): void
    {
        $this->assertTrue((new EmailConfig())->fireEvents);
    }

    public function testManagerHonoursFireEventsFalse(): void
    {
        Events::on('email.sending', static fn (): bool => false);

        $config             = new EmailConfig();
        $config->fireEvents = false;

        $manager = new MailerManager($config);
        $email   = (new Email())->from('me@example.com')->to('you@example.com');

        // With events suppressed, the cancelling listener is ignored and the
        // default (null) transport reports success.
        $this->assertTrue($manager->send($email)->success);
    }

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
        // (no connection is opened until send()); a stable cached instance proves
        // the smtp mailer resolved.
        $manager = new MailerManager($config);
        $this->assertSame($manager->mailer('smtp'), $manager->mailer('smtp'));
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

    public function testConfigShipsFailoverTransport(): void
    {
        $this->assertArrayHasKey('failover', (new EmailConfig())->transports);
    }

    public function testResolvesFailoverChildrenByName(): void
    {
        $config          = new EmailConfig();
        $config->mailers = [
            'broken'   => ['transport' => 'broken'],
            'null'     => ['transport' => 'null'],
            'failover' => ['transport' => 'failover', 'chain' => ['broken', 'null']],
        ];
        // A transport whose send() always fails, so the composite must fall
        // through to the real null transport to succeed.
        $config->transports['broken'] = AlwaysFailsTransport::class;
        $config->default              = 'failover';

        $manager = new MailerManager($config);
        $email   = (new Email())->from('me@example.com')->to('you@example.com');

        $this->assertTrue($manager->send($email)->success);
    }

    public function testFailoverWithoutChildrenThrows(): void
    {
        $config          = new EmailConfig();
        $config->mailers = [
            'failover' => ['transport' => 'failover'],
        ];
        $config->default = 'failover';

        $manager = new MailerManager($config);

        $this->expectException(PostalException::class);
        $manager->mailer();
    }

    public function testWrapsRawLeafWithDkimWhenConfigured(): void
    {
        $config          = new EmailConfig();
        $config->mailers = [
            'signed' => [
                'transport' => 'smtp',
                'host'      => 'smtp.example.com',
                'dkim'      => $this->dkimConfig(),
            ],
        ];
        $config->default = 'signed';

        $transport = $this->getPrivateProperty((new MailerManager($config))->mailer(), 'transport');

        $this->assertInstanceOf(DkimSigningTransport::class, $transport);
    }

    public function testEnablingDkimOnApiTransportThrows(): void
    {
        $config          = new EmailConfig();
        $config->mailers = [
            'signed' => ['transport' => 'ses', 'dkim' => $this->dkimConfig()],
        ];
        $config->default = 'signed';

        $manager = new MailerManager($config);

        // SES signs server-side; enabling DKIM is a config error (and the check
        // fires before SesTransport is even constructed).
        $this->expectException(PostalException::class);
        $manager->mailer();
    }

    public function testEnablingDkimOnMailTransportThrows(): void
    {
        $config          = new EmailConfig();
        $config->mailers = [
            'signed' => ['transport' => 'mail', 'dkim' => $this->dkimConfig()],
        ];
        $config->default = 'signed';

        $manager = new MailerManager($config);

        $this->expectException(PostalException::class);
        $manager->mailer();
    }

    public function testFailoverChildLeafIsDkimSignedWhileCompositeStaysAgnostic(): void
    {
        $config          = new EmailConfig();
        $config->mailers = [
            'primary'  => ['transport' => 'smtp', 'host' => 'smtp.example.com', 'dkim' => $this->dkimConfig()],
            'backup'   => ['transport' => 'null'],
            'failover' => ['transport' => 'failover', 'chain' => ['primary', 'backup']],
        ];
        $config->default = 'failover';

        $composite = $this->getPrivateProperty((new MailerManager($config))->mailer(), 'transport');

        // The composite is not itself wrapped...
        $this->assertInstanceOf(FailoverTransport::class, $composite);

        // ...but its signing child leaf is.
        $children = $this->getPrivateProperty($composite, 'children');
        $this->assertInstanceOf(DkimSigningTransport::class, $children[0]);
    }

    public function testUnknownMailerThrows(): void
    {
        $manager = new MailerManager(new EmailConfig());

        $this->expectException(PostalException::class);
        $manager->mailer('does-not-exist');
    }

    public function testUnknownTransportThrows(): void
    {
        $config          = new EmailConfig();
        $config->mailers = ['broken' => ['transport' => 'ghost']];
        $config->default = 'broken';

        $manager = new MailerManager($config);

        $this->expectException(PostalException::class);
        $manager->mailer();
    }

    /**
     * @return array<string, string>
     */
    private function dkimConfig(): array
    {
        return [
            'domain'     => 'example.com',
            'selector'   => 'postal',
            'privateKey' => dirname(__DIR__) . '/_support/dkim/private.pem',
        ];
    }
}
