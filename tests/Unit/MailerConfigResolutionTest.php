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
use CodeIgniter\Test\CIUnitTestCase;
use Config\Mailer as AppMailerConfig;
use Myth\Postal\MailerManager;

/**
 * @internal
 */
final class MailerConfigResolutionTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Stand in for an application that has published the config: loading the
        // stub puts the very class `spark publish` writes into the Config
        // namespace, where Factories should prefer it over the package default.
        require_once __DIR__ . '/../../stubs/Mailer.php';

        Factories::reset('config');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        Factories::reset('config');
    }

    public function testApplicationConfigTakesPrecedenceOverPackageDefault(): void
    {
        $this->assertSame(AppMailerConfig::class, config('Mailer')::class);
    }

    public function testManagerUsesTheApplicationConfig(): void
    {
        $config                      = config('Mailer');
        $config->mailers['app-only'] = ['transport' => 'log'];
        $config->default             = 'app-only';

        $manager = new MailerManager();

        // Neither the "app-only" mailer nor that default exists in the package
        // config, so resolving both to the same cached instance is only possible
        // if the manager read the application's copy.
        $this->assertSame($manager->mailer('app-only'), $manager->mailer());
    }
}
