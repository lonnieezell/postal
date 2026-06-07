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

/**
 * @internal
 */
final class PostalConfigTest extends CIUnitTestCase
{
    public function testPreviewDisabledByDefault(): void
    {
        $config = new Postal();

        $this->assertFalse($config->enablePreview);
        $this->assertFalse($config->previewEnabled('development'));
    }

    public function testPreviewEnabledOutsideProductionWhenFlagOn(): void
    {
        $config                = new Postal();
        $config->enablePreview = true;

        $this->assertTrue($config->previewEnabled('development'));
        $this->assertTrue($config->previewEnabled('testing'));
    }

    public function testPreviewNeverEnabledInProductionEvenWithFlagOn(): void
    {
        $config                = new Postal();
        $config->enablePreview = true;

        $this->assertFalse($config->previewEnabled('production'));
    }

    public function testPreviewDisabledInProductionWithFlagOff(): void
    {
        $config = new Postal();

        $this->assertFalse($config->previewEnabled('production'));
    }
}
