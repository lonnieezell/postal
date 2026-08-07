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
use Myth\Postal\Markdown\LayoutRenderer;

/**
 * @internal
 */
final class LayoutRendererTest extends CIUnitTestCase
{
    public function testRenderWrapsContentInTheDefaultLayoutWithInlinedStyles(): void
    {
        $html = (new LayoutRenderer())->render('<p>Hello</p>');

        $this->assertStringContainsString('Hello', $html);
        $this->assertMatchesRegularExpression('/<p[^>]+style="[^"]*color:\s*#18181b/', $html);
    }

    public function testRenderUsesTheGivenLayoutOverrideInsteadOfTheDefault(): void
    {
        $html = (new LayoutRenderer())->render('<p>Hello</p>', 'Tests\Views\mail\layouts\custom');

        $this->assertStringContainsString('custom-layout-marker', $html);
        $this->assertStringNotContainsString('container', $html);
    }
}
