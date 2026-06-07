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
use Myth\Postal\Email;
use Myth\Postal\Mailable;
use Myth\Postal\Mailer;
use Myth\Postal\MailerManager;
use Myth\Postal\Transport\FakeTransport;
use Tests\Support\Mailables\WelcomeEmail;

/**
 * @internal
 */
final class MailableTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Services::reset();
    }

    public function testSendComposesTheEmailFromBuild(): void
    {
        $fake = Mailer::fake();

        (new WelcomeEmail('Ada'))->send();

        $fake->assertSent(static fn (Email $email): bool => $email->subject === 'Welcome, Ada'
            && $email->from?->email === 'me@example.com'
            && $email->to[0]->email === 'you@example.com'
            && $email->htmlBody === '<p>Hello Ada</p>'
            && $email->textBody === 'Hello Ada');
    }

    public function testSendTagsTheMailableClass(): void
    {
        $fake = Mailer::fake();

        (new WelcomeEmail())->send();

        $fake->assertSent(static fn (Email $email): bool => $email->mailableClass === WelcomeEmail::class);
    }

    public function testSendReturnsTheTransportResult(): void
    {
        Mailer::fake();

        $result = (new WelcomeEmail())->send();

        $this->assertTrue($result->success);
    }

    public function testBuildRunsLazilyAtSendTime(): void
    {
        $fake = Mailer::fake();

        // Constructing the mailable must not compose or send anything.
        $mailable = new WelcomeEmail();
        $fake->assertNothingSent();

        $mailable->send();
        $fake->assertSentCount(1);
    }

    public function testRenderReturnsTheBuiltEmailWithoutSending(): void
    {
        $fake = Mailer::fake();

        $email = (new WelcomeEmail('Ada'))->render();

        $this->assertSame('Welcome, Ada', $email->subject);
        $this->assertSame('<p>Hello Ada</p>', $email->htmlBody);
        $fake->assertNothingSent();
    }

    public function testTransportSelectsTheNamedMailer(): void
    {
        $recorder = new class (new Mailer(new FakeTransport())) extends MailerManager {
            public ?string $requestedName = null;

            public function __construct(private readonly Mailer $stub)
            {
            }

            public function mailer(?string $name = null): Mailer
            {
                $this->requestedName = $name;

                return $this->stub;
            }
        };
        Services::injectMock('mailer', $recorder);

        $mailable = new class () extends Mailable {
            protected function build(): void
            {
                $this->transport('marketing')->from('me@example.com')->to('you@example.com');
            }
        };
        $mailable->send();

        $this->assertSame('marketing', $recorder->requestedName);
    }
}
