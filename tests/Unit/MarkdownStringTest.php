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
use Myth\Postal\Markdown\MarkdownString;

/**
 * @internal
 */
final class MarkdownStringTest extends CIUnitTestCase
{
    public function testToStringReturnsTheConvertedHtml(): void
    {
        $markdown = new MarkdownString('# Hello', '<h1>Hello</h1>');

        $this->assertSame('<h1>Hello</h1>', (string) $markdown);
    }

    public function testTextDerivesThePlainTextFallbackFromTheRawSource(): void
    {
        $markdown = new MarkdownString('# Hello World', '<h1>Hello World</h1>');

        $this->assertSame('Hello World', $markdown->text());
    }

    public function testTextIsStable(): void
    {
        $markdown = new MarkdownString('# Hello World', '<h1>Hello World</h1>');

        $this->assertSame($markdown->text(), $markdown->text());
    }
}
