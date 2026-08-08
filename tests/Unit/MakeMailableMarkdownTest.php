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
final class MakeMailableMarkdownTest extends CIUnitTestCase
{
    private string $classTarget = APPPATH . 'Mails/MarkdownWelcome.php';
    private string $viewTarget  = APPPATH . 'Views/emails/markdown_welcome.php';

    protected function tearDown(): void
    {
        parent::tearDown();

        Services::reset();

        if (is_file($this->classTarget)) {
            unlink($this->classTarget);
        }
        if (is_dir(APPPATH . 'Mails')) {
            rmdir(APPPATH . 'Mails');
        }
        if (is_file($this->viewTarget)) {
            unlink($this->viewTarget);
        }
        if (is_dir(APPPATH . 'Views/emails')) {
            rmdir(APPPATH . 'Views/emails');
        }
    }

    public function testGeneratesBothTheClassAndTheMarkdownView(): void
    {
        command('make:mailable MarkdownWelcome --markdown');

        $this->assertFileExists($this->classTarget);
        $this->assertFileExists($this->viewTarget);
    }

    public function testWithoutTheFlagNoViewIsGenerated(): void
    {
        command('make:mailable MarkdownWelcome');

        $this->assertFileExists($this->classTarget);
        $this->assertFileDoesNotExist($this->viewTarget);
    }

    public function testGeneratedClassCallsMarkdownInsteadOfHtml(): void
    {
        command('make:mailable MarkdownWelcome --markdown');

        $contents = (string) file_get_contents($this->classTarget);
        $this->assertStringContainsString("->markdown('emails/markdown_welcome')", $contents);
        $this->assertStringNotContainsString("->html('')", $contents);
    }

    public function testGeneratedClassIsRunnable(): void
    {
        command('make:mailable MarkdownWelcome --markdown');

        require_once $this->classTarget;

        $class = 'App\Mails\MarkdownWelcome';
        $this->assertTrue(class_exists($class));

        $mailable = new $class();
        $this->assertInstanceOf(Mailable::class, $mailable);

        $fake = Mailer::fake();
        $mailable->send();
        $fake->assertSentCount(1);
    }

    public function testDoesNotOverwriteTheViewWithoutForce(): void
    {
        command('make:mailable MarkdownWelcome --markdown');
        file_put_contents($this->viewTarget, '# sentinel');

        command('make:mailable MarkdownWelcome --markdown');

        $this->assertStringContainsString('# sentinel', (string) file_get_contents($this->viewTarget));
    }

    public function testForceOverwritesTheExistingView(): void
    {
        command('make:mailable MarkdownWelcome --markdown');
        file_put_contents($this->viewTarget, '# sentinel');

        command('make:mailable MarkdownWelcome --markdown --force');

        $this->assertStringNotContainsString('# sentinel', (string) file_get_contents($this->viewTarget));
    }
}
