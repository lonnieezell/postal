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

namespace Myth\Postal\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\GeneratorTrait;

/**
 * Scaffolds a new Mailable class into the application's Mails directory.
 */
class MakeMailable extends BaseCommand
{
    use GeneratorTrait;

    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Generators';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'make:mailable';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new Mailable class.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:mailable <name> [options]';

    /**
     * The Command's Arguments
     *
     * @var array<string, string>
     */
    protected $arguments = [
        'name' => 'The Mailable class name.',
    ];

    /**
     * The Command's Options
     *
     * @var array<string, string>
     */
    protected $options = [
        '--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".',
        '--force'     => 'Force overwrite existing file.',
        '--markdown'  => 'Also scaffold a markdown view, and generate a build() using ->markdown() instead of ->html().',
    ];

    /**
     * Whether --markdown was passed, set at the top of run() and read by
     * prepare() so the {view} placeholder substitution only applies to
     * markdown-flavored generation.
     */
    private bool $isMarkdown = false;

    /**
     * Actually execute the command.
     *
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $this->isMarkdown = array_key_exists('markdown', $params);

        $this->component    = 'Mailable';
        $this->directory    = 'Mails';
        $this->template     = $this->isMarkdown ? 'mailable_markdown.tpl.php' : 'mailable.tpl.php';
        $this->templatePath = $this->isMarkdown
            ? 'Myth\Postal\Commands\Views\mailable_markdown.tpl.php'
            : 'Myth\Postal\Commands\Views\mailable.tpl.php';

        $this->generateClass($params);

        if ($this->isMarkdown) {
            $this->generateMarkdownView($params);
        }

        return 0;
    }

    /**
     * Scaffolds the markdown view the generated Mailable's build() points at.
     *
     * @param array<int|string, string|null> $params
     */
    private function generateMarkdownView(array $params): void
    {
        helper('inflector');

        $view = 'emails/' . decamelize(class_basename($this->qualifyClassName()));

        $this->template     = 'markdown_view.tpl.php';
        $this->templatePath = 'Myth\Postal\Commands\Views\markdown_view.tpl.php';

        $this->generateView($this->getNamespace() . '\Views\\' . str_replace('/', '\\', $view), $params);
    }

    /**
     * For --markdown generation, injects the {view} placeholder (the
     * markdown view the generated build() points at) alongside the base
     * {namespace}/{class} pair. Non-markdown generation is untouched.
     */
    protected function prepare(string $class): string
    {
        if (! $this->isMarkdown) {
            return $this->parseTemplate($class);
        }

        helper('inflector');

        $view = 'emails/' . decamelize(class_basename($class));

        return $this->parseTemplate($class, ['{view}'], [$view]);
    }
}
