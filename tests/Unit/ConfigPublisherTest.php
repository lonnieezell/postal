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

use CodeIgniter\Config\Factories;
use CodeIgniter\Publisher\Publisher;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Publisher as PublisherConfig;
use Myth\Postal\Publishers\ConfigPublisher;

/**
 * @internal
 */
final class ConfigPublisherTest extends CIUnitTestCase
{
    private string $destination = WRITEPATH . 'config-publisher-test/';

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

        Factories::reset('config');

        if (is_file($this->destination . 'Mailer.php')) {
            unlink($this->destination . 'Mailer.php');
        }

        if (is_dir($this->destination)) {
            rmdir($this->destination);
        }
    }

    public function testSparkPublishDiscoversThePublisher(): void
    {
        // Discovery walks every file under Publishers/ and asks the autoloader
        // about it. With the application's published config already loaded, a
        // stub inside the package's PSR-4 root would be pulled in here and
        // fatal on the redeclaration — which is why it lives in stubs/.
        require_once __DIR__ . '/../../stubs/Mailer.php';

        $discovered = array_filter(
            Publisher::discover(),
            static fn (Publisher $publisher): bool => $publisher instanceof ConfigPublisher,
        );

        $this->assertCount(1, $discovered);
    }

    public function testPublishesMailerConfigIntoTheApplicationNamespace(): void
    {
        $this->assertTrue((new ConfigPublisher(null, $this->destination))->publish());

        $published = file_get_contents($this->destination . 'Mailer.php');

        $this->assertIsString($published);
        $this->assertStringContainsString('namespace Config;', $published);
        $this->assertStringContainsString('class Mailer extends PostalMailer', $published);
    }

    public function testDoesNotOverwriteAnAlreadyPublishedFile(): void
    {
        file_put_contents($this->destination . 'Mailer.php', '<?php // mine');

        $this->assertTrue((new ConfigPublisher(null, $this->destination))->publish());
        $this->assertSame('<?php // mine', file_get_contents($this->destination . 'Mailer.php'));
    }
}
