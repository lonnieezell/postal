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
use Myth\Postal\Mailer;
use Tests\Support\Mailables\MarkdownComponents;
use Tests\Support\Mailables\MarkdownCustomLayout;
use Tests\Support\Mailables\MarkdownWelcome;

/**
 * @internal
 */
final class MailableMarkdownTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Services::reset();
    }

    public function testMarkdownPopulatesHtmlBodyWithConvertedLayoutWrappedInlinedHtml(): void
    {
        $fake = Mailer::fake();

        (new MarkdownWelcome())->send();

        $fake->assertSent(static fn (Email $email): bool => str_contains((string) $email->htmlBody, '>Hello World</h1>')
            && str_contains((string) $email->htmlBody, 'Thanks for signing up.')
            && (bool) preg_match('/<p[^>]+style="[^"]*color:\s*#18181b/', (string) $email->htmlBody));
    }

    public function testMarkdownPopulatesTextBodyFromTheRawMarkdownSource(): void
    {
        $fake = Mailer::fake();

        (new MarkdownWelcome())->send();

        $fake->assertSent(static fn (Email $email): bool => $email->textBody === "Hello World\n\nThanks for signing up.");
    }

    public function testMarkdownIsAssertableThroughFakeByMailableClass(): void
    {
        $fake = Mailer::fake();

        (new MarkdownWelcome())->send();

        $fake->assertSent(MarkdownWelcome::class);
    }

    public function testRenderProducesTheSameMarkdownOutputWithoutSending(): void
    {
        $fake = Mailer::fake();

        $email = (new MarkdownWelcome())->render();

        $this->assertStringContainsString('>Hello World</h1>', (string) $email->htmlBody);
        $this->assertSame("Hello World\n\nThanks for signing up.", $email->textBody);
        $fake->assertNothingSent();
    }

    public function testLayoutOverridesTheDefaultLayoutForThisMailableOnly(): void
    {
        $fake = Mailer::fake();

        (new MarkdownCustomLayout())->send();

        $fake->assertSent(static fn (Email $email): bool => str_contains((string) $email->htmlBody, 'custom-layout-marker')
            && ! str_contains((string) $email->htmlBody, 'class="container"'));
    }

    public function testMarkdownRendersMailButtonAndMailPanelComponentsEndToEnd(): void
    {
        $fake = Mailer::fake();

        (new MarkdownComponents())->send();

        $fake->assertSent(static fn (Email $email): bool => str_contains((string) $email->htmlBody, 'href="https://example.com/confirm"')
            && str_contains((string) $email->htmlBody, 'Confirm Email')
            && str_contains((string) $email->htmlBody, 'Some <strong>bold</strong> text inside the panel.'));
    }
}
