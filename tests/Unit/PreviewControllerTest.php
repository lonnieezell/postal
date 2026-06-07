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
use Myth\Postal\Config\Postal;
use Myth\Postal\Controllers\PreviewController;
use Tests\Support\Mailables\PreviewBroken;
use Tests\Support\Mailables\PreviewHtmlOnly;
use Tests\Support\Mailables\PreviewWelcome;
use Tests\Support\Mailables\WelcomeEmail;

/**
 * @internal
 */
final class PreviewControllerTest extends CIUnitTestCase
{
    private function controller(): PreviewController
    {
        $config                     = new Postal();
        $config->mailableNamespaces = ['Tests\Support\Mailables' => SUPPORTPATH . 'Mailables'];

        return new PreviewController($config);
    }

    public function testDiscoverFindsPreviewableMailables(): void
    {
        $found = $this->controller()->discover();

        $this->assertContains(PreviewWelcome::class, $found);
        $this->assertContains(PreviewHtmlOnly::class, $found);
        $this->assertContains(PreviewBroken::class, $found);
    }

    public function testDiscoverExcludesNonPreviewableMailables(): void
    {
        $found = $this->controller()->discover();

        $this->assertNotContains(WelcomeEmail::class, $found);
    }
}
