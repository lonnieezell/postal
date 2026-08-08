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

use CodeIgniter\Publisher\Publisher;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Publisher as PublisherConfig;
use Myth\Postal\Publishers\ViewPublisher;

/**
 * @internal
 */
final class ViewPublisherTest extends CIUnitTestCase
{
    private string $destination = WRITEPATH . 'view-publisher-test/';

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_dir($this->destination)) {
            mkdir($this->destination, 0775, true);
        }

        // Under the test bootstrap FCPATH is "/", so the framework's default
        // public-directory restriction — which allows only web assets — would
        // reject every .php file wherever it is published. Keep just the
        // project-directory rule, which is the one that applies in a real app.
        config(PublisherConfig::class)->restrictions = [ROOTPATH => '*'];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        self::removeDirectory($this->destination);
    }

    private static function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . $item;

            if (is_dir($path)) {
                self::removeDirectory($path . '/');
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    public function testSparkPublishDiscoversThePublisher(): void
    {
        $discovered = array_filter(
            Publisher::discover(),
            static fn (Publisher $publisher): bool => $publisher instanceof ViewPublisher,
        );

        $this->assertCount(1, $discovered);
    }

    public function testPublishesLayoutAndComponentsPreservingSubdirectories(): void
    {
        $this->assertTrue((new ViewPublisher(null, $this->destination))->publish());

        $this->assertFileExists($this->destination . 'mail/layouts/default.php');
        $this->assertFileExists($this->destination . 'mail/components/panel.php');
        $this->assertFileExists($this->destination . 'mail/components/button.php');

        $this->assertSame(
            file_get_contents(__DIR__ . '/../../src/Views/mail/layouts/default.php'),
            file_get_contents($this->destination . 'mail/layouts/default.php'),
        );
    }

    public function testDoesNotOverwriteAnAlreadyPublishedFile(): void
    {
        mkdir($this->destination . 'mail/layouts', 0775, true);
        file_put_contents($this->destination . 'mail/layouts/default.php', '<?php // mine');

        $this->assertTrue((new ViewPublisher(null, $this->destination))->publish());
        $this->assertSame('<?php // mine', file_get_contents($this->destination . 'mail/layouts/default.php'));
    }

    public function testDoesNotPublishThePreviewViews(): void
    {
        $this->assertTrue((new ViewPublisher(null, $this->destination))->publish());

        $this->assertDirectoryDoesNotExist($this->destination . 'preview');
    }
}
