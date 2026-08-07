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

    public function testMarkdownFixtureWithButtonAndPanelRendersExpectedHtml(): void
    {
        $html = (string) markdown('Tests\Views\markdown\components');

        $this->assertStringContainsString('<h1>Welcome</h1>', $html);
        $this->assertStringContainsString('Confirm Email</a>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<p>Another paragraph.</p>', $html);
    }
}
