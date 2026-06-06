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
use CodeIgniter\Test\StreamFilterTrait;
use Myth\Postal\Mailer;
use Myth\Postal\Transport\TransportInterface;
use Tests\Support\RecordingMailerManager;
use Tests\Support\RecordingTransport;

/**
 * @internal
 */
final class EmailTestCommandTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    protected function tearDown(): void
    {
        parent::tearDown();

        Services::reset();
    }

    public function testSendsThroughDefaultMailerAndPrintsMessageId(): void
    {
        $fake = Mailer::fake();

        command('email:test you@example.com');

        $fake->assertSentTo('you@example.com');
        $this->assertStringContainsString('fake-1', $this->getStreamFilterBuffer());
    }

    public function testPrintsErrorWhenSendFails(): void
    {
        $this->injectMailer(new RecordingTransport(succeed: false, error: 'transport exploded'));

        command('email:test you@example.com');

        $this->assertStringContainsString('transport exploded', $this->getStreamFilterBuffer());
    }

    public function testMailerOptionSelectsNamedMailer(): void
    {
        $manager = $this->recordingManager(new RecordingTransport());

        command('email:test you@example.com --mailer smtp');

        $this->assertSame('smtp', $manager->requestedName);
    }

    public function testTransportOptionSelectsNamedMailer(): void
    {
        $manager = $this->recordingManager(new RecordingTransport());

        command('email:test you@example.com --transport smtp');

        $this->assertSame('smtp', $manager->requestedName);
    }

    public function testUsesFromEmailFromLegacyConfig(): void
    {
        config('Email')->fromEmail = 'sender@example.com';
        config('Email')->fromName  = 'Test Sender';

        $transport = new RecordingTransport();
        $this->injectMailer($transport);

        command('email:test you@example.com');

        $this->assertNotNull($transport->sent[0]->from);
        $this->assertSame('sender@example.com', $transport->sent[0]->from->email);
        $this->assertSame('Test Sender', $transport->sent[0]->from->name);
    }

    /**
     * Swaps service('mailer') for a manager whose default mailer is backed by
     * the given transport.
     */
    private function injectMailer(TransportInterface $transport): void
    {
        $this->recordingManager($transport);
    }

    /**
     * Injects a MailerManager double that records the requested mailer name and
     * always returns a Mailer backed by the given transport (events disabled).
     */
    private function recordingManager(TransportInterface $transport): RecordingMailerManager
    {
        $manager = new RecordingMailerManager($transport);

        Services::injectMock('mailer', $manager);

        return $manager;
    }
}
