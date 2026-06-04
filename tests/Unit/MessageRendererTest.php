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
use Myth\Postal\Email;
use Myth\Postal\MessageRenderer;

/**
 * @internal
 */
final class MessageRendererTest extends CIUnitTestCase
{
    public function testRendersTextOnlyMessage(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject('Hello')
            ->text('Hello there');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('From: me@example.com', $mime);
        $this->assertStringContainsString('To: you@example.com', $mime);
        $this->assertStringContainsString('Subject: Hello', $mime);
        $this->assertStringContainsString('MIME-Version: 1.0', $mime);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $mime);

        // Body follows a blank line that separates headers from content.
        [, $body] = explode("\r\n\r\n", $mime, 2);
        $this->assertStringContainsString('Hello there', $body);
    }

    public function testRendersCcAndReplyToWhenSet(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->cc('boss@example.com')
            ->replyTo('reply@example.com', 'Reply Desk')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('Cc: boss@example.com', $mime);
        $this->assertStringContainsString('Reply-To: "Reply Desk" <reply@example.com>', $mime);
    }

    public function testOmitsCcAndReplyToWhenUnset(): void
    {
        $email = (new Email())->from('me@example.com')->to('you@example.com')->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringNotContainsString('Cc:', $mime);
        $this->assertStringNotContainsString('Reply-To:', $mime);
    }

    public function testEmitsArbitraryCustomHeaders(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->header('X-Campaign', 'spring')
            ->header('X-Mailer', 'Postal')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('X-Campaign: spring', $mime);
        $this->assertStringContainsString('X-Mailer: Postal', $mime);
    }

    public function testReturnPathEmitsReturnPathAndSender(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->returnPath('bounce@example.com')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('Return-Path: <bounce@example.com>', $mime);
        $this->assertStringContainsString('Sender: bounce@example.com', $mime);
    }

    public function testPriorityEmitsXPriorityHeader(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->priority(1)
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('X-Priority: 1 (Highest)', $mime);
    }

    public function testDefaultPriorityIsNotEmitted(): void
    {
        $email = (new Email())->from('me@example.com')->to('you@example.com')->text('Hi');

        $this->assertStringNotContainsString('X-Priority:', (new MessageRenderer())->render($email));
    }

    public function testRendersMultipartAlternativeWhenBothBodiesPresent(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text('Plain version')
            ->html('<p>HTML version</p>');

        $mime = (new MessageRenderer())->render($email);

        $this->assertMatchesRegularExpression(
            '/Content-Type: multipart\/alternative; boundary="(.+)"/',
            $mime,
        );

        preg_match('/boundary="(.+)"/', $mime, $matches);
        $boundary = $matches[1];

        $this->assertStringContainsString('--' . $boundary, $mime);
        $this->assertStringContainsString('--' . $boundary . '--', $mime);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $mime);
        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $mime);

        // The plain-text alternative must precede the HTML part per RFC 2046.
        $this->assertLessThan(
            strpos($mime, 'text/html'),
            strpos($mime, 'text/plain'),
        );

        $this->assertStringContainsString('Plain version', $mime);
        $this->assertStringContainsString('<p>HTML version</p>', $mime);
    }

    public function testHtmlOnlyGeneratesPlainTextFallbackPart(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->html('<p>Hello <a href="https://example.com">our site</a></p>');

        $renderer = new MessageRenderer();
        $mime     = $renderer->render($email);

        // Still multipart/alternative even without an explicit text body.
        $this->assertStringContainsString('Content-Type: multipart/alternative;', $mime);

        $textPart = $this->textPartOf($mime);
        $this->assertStringContainsString('Hello our site (https://example.com)', $textPart);
        $this->assertStringNotContainsString('<a href', $textPart);
        $this->assertStringNotContainsString('<p>', $textPart);

        // The fallback must not be written back onto the Email.
        $this->assertNull($email->textBody);
    }

    public function testHtmlToTextConvertsBlockTagsToNewlinesAndDecodesEntities(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->html('<h1>Title</h1><p>One &amp; two</p><br>Three');

        $textPart = $this->textPartOf((new MessageRenderer())->render($email));

        $this->assertStringContainsString('One & two', $textPart);
        $this->assertStringNotContainsString('&amp;', $textPart);
        // Block-level boundaries become line breaks, not run-together text.
        $this->assertStringNotContainsString('TitleOne', $textPart);
        $this->assertStringNotContainsString('twoThree', $textPart);
    }

    public function testEncodesNonAsciiSubjectWithRfc2047(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject('Café résumé')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('Subject: =?UTF-8?Q?', $mime);
        $this->assertStringNotContainsString('Subject: Café', $mime);
    }

    public function testLeavesAsciiSubjectUnencoded(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject('Plain subject')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('Subject: Plain subject', $mime);
        $this->assertStringNotContainsString('=?UTF-8?Q?', $mime);
    }

    public function testEncodesNonAsciiAddressDisplayName(): void
    {
        $email = (new Email())
            ->from('me@example.com', 'Café Owner')
            ->to('you@example.com')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        // The addr-spec must stay literal; only the display name is encoded.
        $this->assertMatchesRegularExpression('/From: =\?UTF-8\?Q\?.+\?= <me@example\.com>/', $mime);
    }

    public function testQuotesAsciiDisplayNameContainingComma(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('a@b.com', 'Doe, John')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        // The name is wrapped in a quoted-string so the comma cannot split the
        // address list into two recipients.
        $this->assertStringContainsString('To: "Doe, John" <a@b.com>', $mime);
    }

    public function testEscapesQuotesAndBackslashesInAsciiDisplayName(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('a@b.com', 'Quote " and \\ slash')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('To: "Quote \\" and \\\\ slash" <a@b.com>', $mime);
    }

    public function testStillQEncodesNonAsciiDisplayNameWithoutQuoting(): void
    {
        $email = (new Email())
            ->from('me@example.com', 'Café Owner')
            ->to('you@example.com')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        // Non-ASCII names use RFC 2047 Q-encoding, never the quoted-string form.
        $this->assertMatchesRegularExpression('/From: =\?UTF-8\?Q\?.+\?= <me@example\.com>/', $mime);
        $this->assertStringNotContainsString('From: "', $mime);
    }

    public function testWordWrapsLongTextBodyLines(): void
    {
        $longLine = str_repeat('word ', 40); // ~200 chars on a single line

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text($longLine);

        $mime     = (new MessageRenderer())->render($email);
        [, $body] = explode("\r\n\r\n", $mime, 2);

        foreach (explode("\r\n", rtrim($body)) as $line) {
            $this->assertLessThanOrEqual(76, strlen($line));
        }
    }

    public function testStripsCrlfFromHeaderValuesToPreventInjection(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject("Hi\r\nX-Injected: yes")
            ->header('X-Note', "ok\r\nBcc: victim@example.com")
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringNotContainsString("\r\nX-Injected: yes", $mime);
        $this->assertStringNotContainsString("\r\nBcc: victim@example.com", $mime);
    }

    public function testCustomHeadersCannotClobberStructuralHeaders(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->header('From', 'attacker@evil.com')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('From: me@example.com', $mime);
        $this->assertStringNotContainsString('From: attacker@evil.com', $mime);
    }

    public function testHeadersAccessorReturnsLastRenderedHeaderSet(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject('Hello')
            ->header('X-Campaign', 'spring')
            ->text('Hi');

        $renderer = new MessageRenderer();
        $renderer->render($email);

        $headers = $renderer->headers();

        $this->assertSame('me@example.com', $headers['From']);
        $this->assertSame('Hello', $headers['Subject']);
        $this->assertSame('spring', $headers['X-Campaign']);
        $this->assertSame('text/plain; charset=UTF-8', $headers['Content-Type']);
    }

    /**
     * Extracts the text/plain part body from a multipart/alternative message.
     */
    private function textPartOf(string $mime): string
    {
        preg_match('/boundary="(.+)"/', $mime, $matches);
        $parts = explode('--' . $matches[1], $mime);

        foreach ($parts as $part) {
            if (str_contains($part, 'text/plain')) {
                [, $content] = explode("\r\n\r\n", $part, 2);

                return $content;
            }
        }

        return '';
    }
}
