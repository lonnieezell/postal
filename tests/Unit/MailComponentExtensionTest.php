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
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Markdown\MarkdownRenderer;

/**
 * @internal
 */
final class MailComponentExtensionTest extends CIUnitTestCase
{
    public function testSingleLineMailButtonResolvesToTheButtonComponentView(): void
    {
        $html = (new MarkdownRenderer())->toHtml(
            '<mail-button url="https://example.com/confirm">Confirm Email</mail-button>',
        );

        // The URL has to survive verbatim: consumers post-process rendered HTML
        // by matching href="http...", and an over-escaped scheme hides the link
        // from them.
        $this->assertStringContainsString('href="https://example.com/confirm"', $html);
        $this->assertStringContainsString('Confirm Email', $html);
    }

    public function testButtonUrlKeepsItsQueryStringWithTheAmpersandEscapedForHtml(): void
    {
        $html = (new MarkdownRenderer())->toHtml(
            '<mail-button url="https://example.com/r?a=1&b=2">Read</mail-button>',
        );

        $this->assertStringContainsString('href="https://example.com/r?a=1&amp;b=2"', $html);
    }

    public function testButtonUrlCannotInjectMarkupThroughTheHrefAttribute(): void
    {
        $html = (new MarkdownRenderer())->toHtml(
            '<mail-button url="https://example.com/<script>alert(1)</script>">Go</mail-button>',
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testMailButtonWithoutAUrlAttributeThrowsAClearException(): void
    {
        $this->expectException(PostalException::class);
        $this->expectExceptionMessage('<mail-button> requires a "url" attribute.');

        (new MarkdownRenderer())->toHtml('<mail-button>Confirm Email</mail-button>');
    }

    public function testSameLineClosingTagFollowedByTrailingContentFallsBackToRawHtmlInsteadOfSwallowingContent(): void
    {
        // Not the documented single-line usage (nothing may trail the closing
        // tag), so this falls back to CommonMark's own raw-HTML passthrough
        // rather than being claimed as a Mail Component - and, critically,
        // doesn't swallow the rest of the document into an unclosed block.
        $html = (new MarkdownRenderer())->toHtml(
            "<mail-button url=\"https://example.com\">Confirm</mail-button> Thanks!\n\nA later paragraph.",
        );

        $this->assertStringContainsString('Thanks!', $html);
        $this->assertStringContainsString('<p>A later paragraph.</p>', $html);
    }

    public function testMultiLineMailPanelParsesNestedMarkdownInItsBody(): void
    {
        $markdown = <<<'MARKDOWN'
            <mail-panel color="#fef3c7">
            Some **bold** text inside the panel.

            Another paragraph.
            </mail-panel>
            MARKDOWN;

        $html = (new MarkdownRenderer())->toHtml($markdown);

        $this->assertStringContainsString('background-color: #fef3c7', $html);
        $this->assertStringContainsString('<p>Some <strong>bold</strong> text inside the panel.</p>', $html);
        $this->assertStringContainsString('<p>Another paragraph.</p>', $html);
    }

    public function testPanelWithoutAColorUsesTheDefaultBackground(): void
    {
        $html = (new MarkdownRenderer())->toHtml("<mail-panel>\nHello.\n</mail-panel>");

        $this->assertStringContainsString('background-color: #eff6ff', $html);
    }

    public function testPanelColorDoesNotLeakIntoALaterPanel(): void
    {
        // Each component gets its own view render; an optional attribute set on
        // one must not survive into the next component in the same document.
        $markdown = <<<'MARKDOWN'
            <mail-panel color="#fef3c7">
            First.
            </mail-panel>

            <mail-panel>
            Second.
            </mail-panel>
            MARKDOWN;

        $html = (new MarkdownRenderer())->toHtml($markdown);

        $this->assertSame(1, substr_count($html, 'background-color: #fef3c7'));
        $this->assertSame(1, substr_count($html, 'background-color: #eff6ff'));
    }

    public function testPanelColorSupportsAKeyword(): void
    {
        $html = (new MarkdownRenderer())->toHtml("<mail-panel color=\"linen\">\nHello.\n</mail-panel>");

        $this->assertStringContainsString('background-color: linen', $html);
    }

    public function testPanelFallsBackToTheDefaultWhenTheColorIsNotAColor(): void
    {
        // The attribute value is author-supplied and lands inside style="...",
        // so anything that isn't a plain colour must not reach the stylesheet.
        $html = (new MarkdownRenderer())->toHtml(
            "<mail-panel color=\"red; background-image: url(https://evil.test/x)\">\nHello.\n</mail-panel>",
        );

        $this->assertStringNotContainsString('evil.test', $html);
        $this->assertStringContainsString('background-color: #eff6ff', $html);
    }

    public function testNestedMailComponentsInsideAPanelAreParsed(): void
    {
        $markdown = <<<'MARKDOWN'
            <mail-panel>
            <mail-button url="https://example.com">Click</mail-button>
            </mail-panel>
            MARKDOWN;

        $html = (new MarkdownRenderer())->toHtml($markdown);

        $this->assertStringContainsString('Click</a>', $html);
    }

    public function testSingleLineComponentSlotHonoursConfiguredGfmExtensions(): void
    {
        $html = (new MarkdownRenderer())->toHtml(
            '<mail-button url="https://example.com">~~Old~~ New</mail-button>',
        );

        $this->assertStringContainsString('<del>Old</del> New', $html);
    }

    public function testMarkdownFixtureWithButtonAndPanelRendersExpectedHtml(): void
    {
        $html = (string) markdown('Tests\Views\markdown\components');

        $this->assertStringContainsString('<h1>Welcome</h1>', $html);
        $this->assertStringContainsString('Confirm Email</a>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<p>Another paragraph.</p>', $html);
    }
}
