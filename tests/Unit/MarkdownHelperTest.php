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

/**
 * @internal
 */
final class MarkdownHelperTest extends CIUnitTestCase
{
    public function testMarkdownHelperIsAlwaysAvailable(): void
    {
        $this->assertTrue(function_exists('markdown'));
    }

    public function testMarkdownHelperResolvesViewAndConvertsToHtml(): void
    {
        $result = markdown('Tests\Views\markdown\welcome', ['name' => 'World']);

        $this->assertSame(
            "<h1>Hello World</h1>\n<p>Thanks for signing up.</p>\n",
            (string) $result,
        );
    }

    public function testMarkdownHelperTextDerivesFromTheSameRawSource(): void
    {
        $result = markdown('Tests\Views\markdown\welcome', ['name' => 'World']);

        $this->assertSame("Hello World\n\nThanks for signing up.", $result->text());
    }

    public function testMarkdownHelperEscapesInterpolatedData(): void
    {
        $result = markdown('Tests\Views\markdown\welcome', ['name' => '<script>']);

        $this->assertStringContainsString('&lt;script&gt;', (string) $result);
    }
}
