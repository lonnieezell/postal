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

    public function testStripsStyleScriptAndHeadFromTextFallback(): void
    {
        $html = '<html><head><title>Page Title</title>'
            . '<style>p{color:red;font-size:14px}</style></head>'
            . '<body><script>alert("x");var y=1;</script>'
            . '<p>Visible body</p></body></html>';

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->html($html);

        $textPart = $this->textPartOf((new MessageRenderer())->render($email));

        $this->assertStringContainsString('Visible body', $textPart);
        // CSS, JS, and head metadata must never leak into the text part.
        $this->assertStringNotContainsString('color:red', $textPart);
        $this->assertStringNotContainsString('font-size', $textPart);
        $this->assertStringNotContainsString('alert(', $textPart);
        $this->assertStringNotContainsString('var y', $textPart);
        $this->assertStringNotContainsString('Page Title', $textPart);
    }

    public function testStripsHtmlCommentsFromTextFallback(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->html('<p>Before<!-- secret internal note -->After</p>');

        $textPart = $this->textPartOf((new MessageRenderer())->render($email));

        $this->assertStringNotContainsString('secret internal note', $textPart);
    }

    public function testHtmlPartIsQuotedPrintableEncoded(): void
    {
        // An 8-bit char plus a line well over the 998-octet SMTP limit.
        $html = '<p>caf&eacute; ' . str_repeat('x', 1200) . ' café</p>';

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->html($html);

        $mime = (new MessageRenderer())->render($email);

        $this->assertMatchesRegularExpression(
            '/Content-Type: text\/html;[^\r\n]*\r\nContent-Transfer-Encoding: quoted-printable/',
            $mime,
        );

        $htmlPart = $this->htmlPartOf($mime);

        // The 8-bit é is QP-escaped and long lines are soft-wrapped at <=76.
        $this->assertStringContainsString('=C3=A9', $htmlPart);

        foreach (explode("\r\n", rtrim($htmlPart)) as $line) {
            $this->assertLessThanOrEqual(76, strlen($line));
        }

        // The encoding is lossless: decoding restores the original HTML (CRLF).
        $expected = str_replace("\n", "\r\n", $html);
        $this->assertSame($expected, quoted_printable_decode($htmlPart));
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

    public function testQuotedDisplayNameCannotInjectHeaders(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('a@b.com', "Doe, John\r\nBcc: victim@example.com")
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        // A CRLF in the name must not open a new header line.
        $this->assertStringNotContainsString("\r\nBcc: victim@example.com", $mime);
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

    public function testDoesNotHardWrapTextBodyByDefault(): void
    {
        $longLine = str_repeat('word ', 40); // ~200 chars, no newlines

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text($longLine);

        $mime     = (new MessageRenderer())->render($email);
        [, $body] = explode("\r\n\r\n", $mime, 2);

        // QP soft-wrapping decodes away, so the delivered text is unchanged: a
        // single long line with no hard line breaks introduced by the renderer.
        $this->assertSame(rtrim($longLine), rtrim(quoted_printable_decode($body)));
    }

    public function testHardWrapsTextBodyWhenWordWrapEnabled(): void
    {
        $longLine = str_repeat('word ', 40); // ~200 chars, no newlines

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text($longLine);
        $email->wordWrap  = true;
        $email->wrapChars = 30;

        $mime     = (new MessageRenderer())->render($email);
        [, $body] = explode("\r\n\r\n", $mime, 2);

        $decoded = quoted_printable_decode($body);
        $lines   = explode("\r\n", rtrim($decoded));

        // The wrap survives QP decoding (hard newlines), unlike the soft wrap.
        $this->assertGreaterThan(1, count($lines));

        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(30, strlen($line));
        }
    }

    public function testWordWrapDoesNotBreakLongUnbrokenTokens(): void
    {
        $token = str_repeat('x', 120); // a single word longer than wrapChars

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text($token);
        $email->wordWrap  = true;
        $email->wrapChars = 30;

        $mime     = (new MessageRenderer())->render($email);
        [, $body] = explode("\r\n\r\n", $mime, 2);

        // A long, space-less token (e.g. a URL) is left intact rather than cut.
        $this->assertSame($token, rtrim(quoted_printable_decode($body)));
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

    public function testTextOnlyPartIsQuotedPrintableEncoded(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text('Café');

        $mime     = (new MessageRenderer())->render($email);
        [, $body] = explode("\r\n\r\n", $mime, 2);

        $this->assertStringContainsString('Content-Transfer-Encoding: quoted-printable', $mime);
        // The 8-bit é is QP-escaped, so the part no longer relies on 8BITMIME.
        $this->assertStringContainsString('Caf=C3=A9', $body);
        $this->assertSame('Café', quoted_printable_decode($body));
    }

    public function testTextPartUnbreakableLongTokenStaysWithinLineLimit(): void
    {
        // A single token with no spaces would overflow the 998-octet SMTP limit
        // if it were shipped as raw 8bit; QP soft-wrapping must keep it in check.
        $token = str_repeat('x', 1200);

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text($token);

        $mime     = (new MessageRenderer())->render($email);
        [, $body] = explode("\r\n\r\n", $mime, 2);

        foreach (explode("\r\n", rtrim($body)) as $line) {
            $this->assertLessThanOrEqual(76, strlen($line));
        }

        $this->assertSame($token, quoted_printable_decode($body));
    }

    public function testMultipartTextPartIsQuotedPrintableEncoded(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text('Café plain')
            ->html('<p>hi</p>');

        $textPart = $this->textPartOf((new MessageRenderer())->render($email));

        $this->assertStringContainsString('Caf=C3=A9', $textPart);
        $this->assertSame('Café plain', quoted_printable_decode(rtrim($textPart, "\r\n")));
    }

    public function testCustomContentHeaderCannotAppearOnMultipartContainer(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->html('<p>hi</p>')
            ->header('Content-Transfer-Encoding', 'base64');

        $mime          = (new MessageRenderer())->render($email);
        [$headerBlock] = explode("\r\n\r\n", $mime, 2);

        // A multipart container carries no top-level Content-Transfer-Encoding;
        // a custom one must not leak onto it.
        $this->assertStringNotContainsString('Content-Transfer-Encoding', $headerBlock);
    }

    public function testHtmlToTextHandlesUnquotedAnchorHref(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->html('<p>Visit <a href=https://example.com>our site</a></p>');

        $textPart = $this->textPartOf((new MessageRenderer())->render($email));

        $this->assertStringContainsString('our site (https://example.com)', $textPart);
    }

    public function testAttachmentProducesMultipartMixedWithBase64Part(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text('See attached')
            ->attachData('PDF-BYTES', 'doc.pdf', 'application/pdf');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('Content-Type: multipart/mixed;', $mime);

        $mixed = $this->boundaryOf($mime, 'multipart/mixed');
        $this->assertNotSame('', $mixed);

        // The textual body and the attachment are sibling parts of the mixed container.
        $this->assertStringContainsString('Content-Type: application/pdf; name="doc.pdf"', $mime);
        $this->assertStringContainsString('Content-Transfer-Encoding: base64', $mime);
        $this->assertStringContainsString('Content-Disposition: attachment; filename="doc.pdf"', $mime);

        $part = $this->partWith($mime, $mixed, 'application/pdf');
        $this->assertSame('PDF-BYTES', base64_decode($part, true));

        // The plain-text body survives as the first part of the mixed container.
        $this->assertStringContainsString('See attached', quoted_printable_decode($this->partWith($mime, $mixed, 'text/plain')));
    }

    public function testEmbeddedImageProducesMultipartRelatedWithContentId(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'postal');
        file_put_contents($path, 'PNGDATA');

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->html('<p><img src="cid:logo123"></p>')
            ->embedImage($path, 'logo123', 'logo.png', 'image/png');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('Content-Type: multipart/related;', $mime);
        $this->assertStringContainsString('Content-ID: <logo123>', $mime);
        $this->assertStringContainsString('Content-Disposition: inline; filename="logo.png"', $mime);

        $related = $this->boundaryOf($mime, 'multipart/related');
        $this->assertSame('PNGDATA', base64_decode($this->partWith($mime, $related, 'image/png'), true));

        unlink($path);
    }

    public function testRelatedNestsAlternativeAsItsRootPart(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'postal');
        file_put_contents($path, 'PNGDATA');

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text('plain')
            ->html('<p><img src="cid:logo123"></p>')
            ->embedImage($path, 'logo123', 'logo.png', 'image/png');

        $mime = (new MessageRenderer())->render($email);

        // multipart/related declares its root type and wraps the alternative.
        $this->assertMatchesRegularExpression('#Content-Type: multipart/related;[^\r\n]*type="multipart/alternative"#', $mime);
        $this->assertStringContainsString('Content-Type: multipart/alternative;', $mime);
        // The alternative container appears before the inline image inside related.
        $this->assertLessThan(strpos($mime, 'image/png'), strpos($mime, 'multipart/alternative'));

        unlink($path);
    }

    public function testAttachmentAndInlineImageNestMixedAroundRelated(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'postal');
        file_put_contents($path, 'PNGDATA');

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->html('<p><img src="cid:logo123"></p>')
            ->embedImage($path, 'logo123', 'logo.png', 'image/png')
            ->attachData('PDF-BYTES', 'doc.pdf', 'application/pdf');

        $mime = (new MessageRenderer())->render($email);

        // mixed { related { alternative }, attachment }. Per RFC 2046 the mixed
        // container carries no type parameter; only the related one does (RFC 2387).
        $this->assertStringContainsString('Content-Type: multipart/mixed; boundary="', $mime);
        $this->assertMatchesRegularExpression('#Content-Type: multipart/related;[^\r\n]*type="multipart/alternative"#', $mime);

        // The related container (with the inline image) precedes the attachment.
        $this->assertLessThan(strpos($mime, 'application/pdf'), strpos($mime, 'multipart/related'));
        $this->assertLessThan(strpos($mime, 'application/pdf'), strpos($mime, 'image/png'));

        unlink($path);
    }

    public function testBase64BinaryPartIsChunkedAtSeventySixColumns(): void
    {
        $data = random_bytes(200); // base64 ~268 chars, must wrap

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text('hi')
            ->attachData($data, 'blob.bin', 'application/octet-stream');

        $mime  = (new MessageRenderer())->render($email);
        $mixed = $this->boundaryOf($mime, 'multipart/mixed');
        $part  = $this->partWith($mime, $mixed, 'application/octet-stream');

        foreach (explode("\r\n", rtrim($part)) as $line) {
            $this->assertLessThanOrEqual(76, strlen($line));
        }

        // Chunking is lossless: decoding the whole part restores the bytes.
        $this->assertSame($data, base64_decode(str_replace("\r\n", '', $part), true));
    }

    public function testPathAttachmentIsReadLazilyAtRender(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'postal');
        file_put_contents($path, 'ON-DISK');

        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text('hi')
            ->attach($path, 'data.txt', 'text/plain');

        $mime  = (new MessageRenderer())->render($email);
        $mixed = $this->boundaryOf($mime, 'multipart/mixed');

        $this->assertSame('ON-DISK', base64_decode($this->partWith($mime, $mixed, 'data.txt'), true));

        unlink($path);
    }

    public function testAttachmentFilenameCannotInjectPartHeaders(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text('hi')
            ->attachData('x', "evil.txt\r\nContent-Type: text/evil", 'text/plain');

        $mime = (new MessageRenderer())->render($email);

        // A CRLF embedded in the filename must not open a new header line.
        $this->assertStringNotContainsString("\r\nContent-Type: text/evil", $mime);
    }

    public function testNoAttachmentsLeavesMessageUnwrapped(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->text('hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringNotContainsString('multipart/mixed', $mime);
        $this->assertStringNotContainsString('multipart/related', $mime);
    }

    /**
     * Returns the boundary declared for the given multipart content subtype.
     */
    private function boundaryOf(string $mime, string $type): string
    {
        preg_match('#Content-Type: ' . preg_quote($type, '#') . ';[^\r\n]*boundary="([^"]+)"#', $mime, $m);

        return $m[1] ?? '';
    }

    /**
     * Returns the (rtrimmed) body of the first part delimited by $boundary whose
     * block contains $needle.
     */
    private function partWith(string $mime, string $boundary, string $needle): string
    {
        foreach (explode('--' . $boundary, $mime) as $chunk) {
            if (str_contains($chunk, $needle)) {
                $split = explode("\r\n\r\n", $chunk, 2);

                return rtrim($split[1] ?? '', "\r\n");
            }
        }

        return '';
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

    /**
     * Extracts the text/html part body from a multipart/alternative message.
     */
    private function htmlPartOf(string $mime): string
    {
        preg_match('/boundary="(.+)"/', $mime, $matches);
        $parts = explode('--' . $matches[1], $mime);

        foreach ($parts as $part) {
            if (str_contains($part, 'text/html')) {
                [, $content] = explode("\r\n\r\n", $part, 2);

                return rtrim($content, "\r\n");
            }
        }

        return '';
    }
}
