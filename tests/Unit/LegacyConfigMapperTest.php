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
use Myth\Postal\LegacyConfigMapper;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class LegacyConfigMapperTest extends CIUnitTestCase
{
    public function testSmtpWithCredentialsIncludesAuthType(): void
    {
        $mailer = LegacyConfigMapper::toMailer([
            'protocol'       => 'smtp',
            'SMTPHost'       => 'smtp.example.com',
            'SMTPPort'       => 587,
            'SMTPUser'       => 'user',
            'SMTPPass'       => 'secret',
            'SMTPCrypto'     => 'ssl',
            'SMTPTimeout'    => 10,
            'SMTPKeepAlive'  => true,
            'SMTPAuthMethod' => 'plain',
        ]);

        $this->assertSame([
            'transport'  => 'smtp',
            'host'       => 'smtp.example.com',
            'port'       => 587,
            'timeout'    => 10,
            'encryption' => 'ssl',
            'keepAlive'  => true,
            'username'   => 'user',
            'password'   => 'secret',
            'authType'   => 'plain',
        ], $mailer);
    }

    /**
     * @param array<string, string> $credentials
     */
    #[DataProvider('provideSmtpOmitsCredentialsUnlessBothSet')]
    public function testSmtpOmitsCredentialsUnlessBothSet(array $credentials): void
    {
        $mailer = LegacyConfigMapper::toMailer(['protocol' => 'smtp'] + $credentials);

        $this->assertArrayNotHasKey('username', $mailer);
        $this->assertArrayNotHasKey('password', $mailer);
        $this->assertArrayNotHasKey('authType', $mailer);
    }

    /**
     * @return iterable<string, array{0: array<string, string>}>
     */
    public static function provideSmtpOmitsCredentialsUnlessBothSet(): iterable
    {
        yield 'none' => [[]];

        yield 'user only' => [['SMTPUser' => 'user']];

        yield 'password only' => [['SMTPPass' => 'secret']];
    }

    public function testDsnOnAddsDeliveryStatusNotification(): void
    {
        $mailer = LegacyConfigMapper::toMailer(['protocol' => 'smtp', 'DSN' => true]);

        $this->assertSame(['notify' => 'SUCCESS,FAILURE,DELAY', 'ret' => 'FULL'], $mailer['dsn']);
    }

    public function testDsnOffOmitsDsn(): void
    {
        $mailer = LegacyConfigMapper::toMailer(['protocol' => 'smtp', 'DSN' => false]);

        $this->assertArrayNotHasKey('dsn', $mailer);
    }

    public function testSendmail(): void
    {
        $mailer = LegacyConfigMapper::toMailer([
            'protocol' => 'sendmail',
            'mailPath' => '/opt/sendmail',
            'SMTPHost' => 'ignored.example.com',
            'DSN'      => true,
        ]);

        $this->assertSame(['transport' => 'sendmail', 'path' => '/opt/sendmail'], $mailer);
    }

    public function testMail(): void
    {
        $mailer = LegacyConfigMapper::toMailer(['protocol' => 'mail', 'SMTPHost' => 'ignored.example.com']);

        $this->assertSame(['transport' => 'mail'], $mailer);
    }

    public function testUnknownProtocolFallsBackToMail(): void
    {
        $this->assertSame(['transport' => 'mail'], LegacyConfigMapper::toMailer(['protocol' => 'carrier-pigeon']));
    }

    public function testProtocolIsCaseInsensitive(): void
    {
        $this->assertSame('smtp', LegacyConfigMapper::toMailer(['protocol' => 'SMTP'])['transport']);
    }

    public function testDatabaseStyleStringsAreCast(): void
    {
        $mailer = LegacyConfigMapper::toMailer([
            'protocol'      => 'smtp',
            'SMTPPort'      => '587',
            'SMTPTimeout'   => '30',
            'SMTPKeepAlive' => 't',
            'DSN'           => '1',
        ]);

        $this->assertSame(587, $mailer['port']);
        $this->assertSame(30, $mailer['timeout']);
        $this->assertTrue($mailer['keepAlive']);
        $this->assertArrayHasKey('dsn', $mailer);
    }

    #[DataProvider('provideFalseyStringsCastToFalse')]
    public function testFalseyStringsCastToFalse(string $value): void
    {
        $mailer = LegacyConfigMapper::toMailer(['protocol' => 'smtp', 'SMTPKeepAlive' => $value, 'DSN' => $value]);

        $this->assertFalse($mailer['keepAlive']);
        $this->assertArrayNotHasKey('dsn', $mailer);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideFalseyStringsCastToFalse(): iterable
    {
        foreach (['0', 'false', '', 'f'] as $value) {
            yield "'{$value}'" => [$value];
        }
    }

    public function testMissingKeysFallBackToConfigEmailDefaults(): void
    {
        $this->assertSame([
            'transport'  => 'smtp',
            'host'       => '',
            'port'       => 25,
            'timeout'    => 5,
            'encryption' => 'tls',
            'keepAlive'  => false,
        ], LegacyConfigMapper::toMailer(['protocol' => 'smtp']));

        $this->assertSame(
            ['transport' => 'sendmail', 'path' => '/usr/sbin/sendmail'],
            LegacyConfigMapper::toMailer(['protocol' => 'sendmail']),
        );

        $this->assertSame(['transport' => 'mail'], LegacyConfigMapper::toMailer([]));
    }

    public function testSmtpWithCredentialsDefaultsAuthTypeToLogin(): void
    {
        $mailer = LegacyConfigMapper::toMailer(['protocol' => 'smtp', 'SMTPUser' => 'user', 'SMTPPass' => 'secret']);

        $this->assertSame('login', $mailer['authType']);
    }
}
