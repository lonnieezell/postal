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
final class MarkdownRendererTest extends CIUnitTestCase
{
    public function testToHtmlRendersHeadings(): void
    {
        $html = (new MarkdownRenderer())->toHtml('# Hello World');

        $this->assertSame("<h1>Hello World</h1>\n", $html);
    }

    public function testToHtmlRendersLinks(): void
    {
        $html = (new MarkdownRenderer())->toHtml('Check out [CodeIgniter](https://codeigniter.com) today.');

        $this->assertSame(
            "<p>Check out <a href=\"https://codeigniter.com\">CodeIgniter</a> today.</p>\n",
            $html,
        );
    }

    public function testToHtmlRendersLists(): void
    {
        $html = (new MarkdownRenderer())->toHtml("- One\n- Two\n- Three");

        $this->assertSame(
            "<ul>\n<li>One</li>\n<li>Two</li>\n<li>Three</li>\n</ul>\n",
            $html,
        );
    }

    public function testToHtmlRendersGfmTables(): void
    {
        $html = (new MarkdownRenderer())->toHtml("| A | B |\n| --- | --- |\n| 1 | 2 |");

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>A</th>', $html);
        $this->assertStringContainsString('<td>1</td>', $html);
    }

    public function testToHtmlRendersGfmStrikethroughAndAutolink(): void
    {
        $html = (new MarkdownRenderer())->toHtml('~~gone~~ and https://example.com');

        $this->assertStringContainsString('<del>gone</del>', $html);
        $this->assertStringContainsString('<a href="https://example.com">https://example.com</a>', $html);
    }

    public function testToTextStripsHeadingMarkers(): void
    {
        $text = (new MarkdownRenderer())->toText("# Hello World\n\nSome text.");

        $this->assertSame("Hello World\n\nSome text.", $text);
    }

    public function testToTextRewritesLinksAsTextWithUrlInParens(): void
    {
        $text = (new MarkdownRenderer())->toText('Check out [CodeIgniter](https://codeigniter.com) today.');

        $this->assertSame('Check out CodeIgniter (https://codeigniter.com) today.', $text);
    }

    public function testToTextNormalizesListMarkers(): void
    {
        $text = (new MarkdownRenderer())->toText("* One\n+ Two\n- Three");

        $this->assertSame("- One\n- Two\n- Three", $text);
    }

    public function testToTextStripsGfmTableFormatting(): void
    {
        $text = (new MarkdownRenderer())->toText("| A | B |\n| --- | --- |\n| 1 | 2 |");

        $this->assertSame("A | B\n1 | 2", $text);
    }

    public function testToTextStripsEmphasisAndInlineCode(): void
    {
        $text = (new MarkdownRenderer())->toText('This is **bold**, *italic*, and `code`.');

        $this->assertSame('This is bold, italic, and code.', $text);
    }

    public function testToTextLeavesStrayAsterisksThatArentEmphasisAlone(): void
    {
        $text = (new MarkdownRenderer())->toText('The total is 3 * 4 * 5 dollars');

        $this->assertSame('The total is 3 * 4 * 5 dollars', $text);
    }

    public function testToTextStripsEmphasisSpanningAHandWrappedLineBreak(): void
    {
        $text = (new MarkdownRenderer())->toText("This is **bold\nspanning lines** text");

        $this->assertSame("This is bold\nspanning lines text", $text);
    }

    public function testToTextDoesNotTreatABareDashLineAsATableSeparator(): void
    {
        $text = (new MarkdownRenderer())->toText("Some intro\n\n--\n\nMore text");

        $this->assertSame("Some intro\n\n--\n\nMore text", $text);
    }

    public function testToTextStripsAllLevelsOfANestedBlockquote(): void
    {
        $text = (new MarkdownRenderer())->toText('> > nested quote');

        $this->assertSame('nested quote', $text);
    }
}
