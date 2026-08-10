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

        $this->assertMatchesRegularExpression('/href="[^"]*"/', $html, 'expected an href attribute');
        $this->assertSame('https://example.com/confirm', html_entity_decode(
            (string) preg_replace('/^.*href="([^"]*)".*$/s', '$1', $html),
        ));
        $this->assertStringContainsString('Confirm Email', $html);
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

        // The color attribute is emitted through esc(..., 'css'), so a literal
        // "#fef3c7" is expected to come back CSS-escaped, not verbatim.
        $this->assertStringContainsString('fef3c7', $html);
        $this->assertStringContainsString('<p>Some <strong>bold</strong> text inside the panel.</p>', $html);
        $this->assertStringContainsString('<p>Another paragraph.</p>', $html);
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
