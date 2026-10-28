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
use CodeIgniter\Email\Email;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Email as LegacyConfig;
use Myth\Postal\LegacyEmailAdapter;
use ReflectionClass;
use ReflectionMethod;
use Tests\Support\RecordingTransport;

/**
 * @internal
 */
final class ShieldEmailerTest extends CIUnitTestCase
{
    private RecordingTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new RecordingTransport();
        Services::injectMock('email', new LegacyEmailAdapter(new LegacyConfig(), $this->transport));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Events::removeAllListeners('email.sending');
    }

    /**
     * Mirrors Shield's emailer() helper: a declared Email return type and an
     * initialize() call with the flat Config\Email keys.
     */
    private function emailer(): Email
    {
        return service('email')->initialize(get_object_vars(new LegacyConfig()));
    }

    public function testEmailerSendsThroughPostalTransport(): void
    {
        $email = $this->emailer()
            ->setFrom('me@example.com', 'Me')
            ->setTo('you@example.com')
            ->setSubject('Activate your account')
            ->setMessage('<p>Your code is 123456</p>');

        $this->assertTrue($email->send(false));
        $this->assertCount(1, $this->transport->sent);
        $this->assertSame('you@example.com', $this->transport->sent[0]->to[0]->email);
        $this->assertSame('Activate your account', $this->transport->sent[0]->subject);
    }

    public function testEmailerSendFiresPostalEvents(): void
    {
        Events::on('email.sending', static fn (): bool => false);

        $email = $this->emailer()
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setSubject('Reset your password')
            ->setMessage('Your code is 123456');

        $this->assertFalse($email->send(false));
        $this->assertCount(0, $this->transport->sent);
    }

    public function testEveryPublicFrameworkMethodIsOverridden(): void
    {
        // Pure helpers that never touch the framework's sender.
        $safe = ['wordWrap', '__destruct'];

        $inherited = [];

        foreach ((new ReflectionClass(LegacyEmailAdapter::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class === Email::class && ! in_array($method->name, $safe, true)) {
                $inherited[] = $method->name;
            }
        }

        $this->assertSame([], $inherited, 'These framework methods would bypass Postal.');
    }

    public function testEmailerStartsFreshAfterAFailedSend(): void
    {
        $failed = $this->emailer()
            ->setFrom('me@example.com')
            ->setTo('not-an-address')
            ->setMessage('First');

        $this->assertFalse($failed->send(false));

        $email = $this->emailer()
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setMessage('Second');

        $this->assertTrue($email->send(false));
        $this->assertSame('you@example.com', $this->transport->sent[0]->to[0]->email);
    }

    public function testInitializeAcceptsConfigObject(): void
    {
        $config           = new LegacyConfig();
        $config->mailType = 'html';

        service('email')->initialize($config)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setMessage('<p>Hi</p>')
            ->send();

        $this->assertSame('<p>Hi</p>', $this->transport->sent[0]->htmlBody);
    }
}
