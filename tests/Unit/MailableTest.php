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
use Myth\Postal\Email;
use Myth\Postal\Mailable;
use Myth\Postal\Mailer;
use Myth\Postal\MailerManager;
use Myth\Postal\Transport\FakeTransport;
use Tests\Support\AlwaysFailsTransport;
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

        foreach (['email.sent', 'email.failed'] as $event) {
            Events::removeAllListeners($event);
        }
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

    public function testSentListenersSeeTheMailableClass(): void
    {
        Mailer::fake();
        $seen = 'not-fired';

        Events::on('email.sent', static function (Email $message) use (&$seen): void {
            $seen = $message->mailableClass;
        });

        (new WelcomeEmail())->send();

        $this->assertSame(WelcomeEmail::class, $seen);
    }

    public function testFailedListenersSeeTheMailableClass(): void
    {
        $seen = 'not-fired';

        Events::on('email.failed', static function (Email $message) use (&$seen): void {
            $seen = $message->mailableClass;
        });

        (new Mailer(new AlwaysFailsTransport()))->send((new WelcomeEmail())->render());

        $this->assertSame(WelcomeEmail::class, $seen);
    }

    public function testPlainEmailSendCarriesNoMailableClass(): void
    {
        $fake = Mailer::fake();

        service('mailer')->mailer()->send((new Email())->from('me@example.com')->to('you@example.com'));

        $fake->assertSent(static fn (Email $email): bool => $email->mailableClass === null);
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

    public function testRenderThenSendBuildsOnlyOnce(): void
    {
        $fake = Mailer::fake();

        $mailable = new WelcomeEmail();
        $mailable->render();
        $mailable->send();

        $fake->assertSent(static fn (Email $email): bool => count($email->to) === 1);
    }

    public function testSendingTwiceBuildsOnlyOnce(): void
    {
        $fake = Mailer::fake();

        $mailable = new WelcomeEmail();
        $mailable->send();
        $mailable->send();

        $fake->assertSentCount(2);
        $fake->assertNotSent(static fn (Email $email): bool => count($email->to) !== 1);
    }

    public function testRenderReturnsTheSameEmailOnRepeatedCalls(): void
    {
        $mailable = new WelcomeEmail();

        $this->assertSame($mailable->render(), $mailable->render());
    }

    public function testRenderTagsTheMailableClass(): void
    {
        $email = (new WelcomeEmail())->render();

        $this->assertSame(WelcomeEmail::class, $email->mailableClass);
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
