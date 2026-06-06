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

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Mailable;
use Myth\Postal\Mailer;

/**
 * @internal
 */
final class MakeMailableTest extends CIUnitTestCase
{
    private string $target = APPPATH . 'Mails/Welcome.php';

    protected function tearDown(): void
    {
        parent::tearDown();

        Services::reset();

        if (is_file($this->target)) {
            unlink($this->target);
        }
        if (is_dir(APPPATH . 'Mails')) {
            rmdir(APPPATH . 'Mails');
        }
    }

    public function testGeneratesAMailableInTheAppMailsNamespace(): void
    {
        command('make:mailable Welcome');

        $this->assertFileExists($this->target);

        $contents = file_get_contents($this->target);
        $this->assertStringContainsString('namespace App\Mails;', (string) $contents);
        $this->assertStringContainsString('class Welcome extends Mailable', (string) $contents);
    }

    public function testGeneratedClassIsRunnable(): void
    {
        command('make:mailable Welcome');

        require_once $this->target;

        $class = 'App\Mails\Welcome';
        $this->assertTrue(class_exists($class));

        $mailable = new $class();
        $this->assertInstanceOf(Mailable::class, $mailable);

        $fake = Mailer::fake();
        $mailable->send();
        $fake->assertSentCount(1);
    }

    public function testDoesNotOverwriteWithoutForce(): void
    {
        command('make:mailable Welcome');
        file_put_contents($this->target, '<?php // sentinel');

        command('make:mailable Welcome');

        $this->assertStringContainsString('// sentinel', (string) file_get_contents($this->target));
    }

    public function testForceOverwritesExistingFile(): void
    {
        command('make:mailable Welcome');
        file_put_contents($this->target, '<?php // sentinel');

        command('make:mailable Welcome --force');

        $this->assertStringNotContainsString('// sentinel', (string) file_get_contents($this->target));
        $this->assertStringContainsString('class Welcome extends Mailable', (string) file_get_contents($this->target));
    }
}
