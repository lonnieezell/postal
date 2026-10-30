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

use CodeIgniter\Config\Factories;
use CodeIgniter\Config\Services;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Email as LegacyConfig;
use Myth\Postal\Config\Mailer as MailerConfig;
use Myth\Postal\LegacyEmailAdapter;
use Myth\Postal\Mailer;
use Myth\Postal\Transport\FakeTransport;
use Tests\Support\AppMailerConfig;
use Tests\Support\RecordingMailerManager;
use Tests\Support\RecordingTransport;

/**
 * @internal
 */
final class LegacyEmailAdapterMailerRoutingTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Factories::reset('config');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Factories::reset('config');
        Events::removeAllListeners('email.sent');
    }

    private function useAppMailerConfig(): void
    {
        Factories::define('config', 'Mailer', AppMailerConfig::class);
    }

    private function send(LegacyEmailAdapter $adapter): bool
    {
        return $adapter
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setSubject('Hello')
            ->setMessage('Hi')
            ->send();
    }

    public function testServiceEmailSendsThroughDefaultMailerWithAppConfig(): void
    {
        $this->useAppMailerConfig();

        $transport = new RecordingTransport();
        $manager   = new RecordingMailerManager($transport);
        Services::injectMock('mailer', $manager);
        Services::resetSingle('email');

        $this->assertTrue($this->send(service('email')));
        $this->assertCount(1, $transport->sent);
        $this->assertNull($manager->requestedName, 'The default mailer should be used.');
    }

    public function testFlatTransportKeysFromInitializeAreIgnoredWithAppConfig(): void
    {
        $this->useAppMailerConfig();
        $fake = Mailer::fake();

        $adapter = new LegacyEmailAdapter(new LegacyConfig());
        $adapter->initialize(['protocol' => 'smtp', 'SMTPHost' => 'smtp.invalid', 'mailPath' => '/nope']);
        $adapter->setProtocol('sendmail');

        $this->assertTrue($this->send($adapter));
        $fake->assertSentCount(1);
    }

    public function testAdapterSendsThroughMailerFireEventsWithAppConfig(): void
    {
        $this->assertSame([true], $this->sentEventsWithFireEvents(true));
    }

    public function testAppFireEventsSettingAppliesToAdapterSends(): void
    {
        $this->assertSame([], $this->sentEventsWithFireEvents(false));
    }

    /**
     * Sends through the real default mailer (the null transport) with the
     * app config's fireEvents set, returning the email.sent events seen.
     *
     * @return list<true>
     */
    private function sentEventsWithFireEvents(bool $fireEvents): array
    {
        $this->useAppMailerConfig();
        config('Mailer')->fireEvents = $fireEvents;
        Services::resetSingle('mailer');

        $sent = [];
        Events::on('email.sent', static function () use (&$sent): void {
            $sent[] = true;
        });

        $this->assertTrue($this->send(new LegacyEmailAdapter(new LegacyConfig())));

        return $sent;
    }

    public function testBccBatchesGoThroughDefaultMailerWithAppConfig(): void
    {
        $this->useAppMailerConfig();
        $fake = Mailer::fake();

        $adapter = new LegacyEmailAdapter(new LegacyConfig());
        $adapter->initialize(['BCCBatchMode' => true, 'BCCBatchSize' => 1]);
        $adapter->setBCC('a@example.com, b@example.com');

        $this->assertTrue($this->send($adapter));
        $fake->assertSentCount(2);
    }

    public function testTransportOverrideWinsOverAppConfig(): void
    {
        $this->useAppMailerConfig();
        $fake      = Mailer::fake();
        $transport = new RecordingTransport();

        $this->assertTrue($this->send(new LegacyEmailAdapter(new LegacyConfig(), $transport)));
        $this->assertCount(1, $transport->sent);
        $fake->assertNothingSent();
    }

    public function testFlatConfigIsUsedWithoutAppConfig(): void
    {
        // Pin the package default: another test may have loaded an app Config\Mailer.
        Factories::define('config', 'Mailer', MailerConfig::class);
        Mailer::fake();

        $mailer = $this->getPrivateMethodInvoker(new LegacyEmailAdapter(new LegacyConfig()), 'buildMailer')();

        $this->assertNotInstanceOf(FakeTransport::class, $this->getPrivateProperty($mailer, 'transport'));
    }
}
