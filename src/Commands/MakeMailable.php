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
    ];

    /**
     * Actually execute the command.
     *
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        $this->component    = 'Mailable';
        $this->directory    = 'Mails';
        $this->template     = 'mailable.tpl.php';
        $this->templatePath = 'Myth\Postal\Commands\Views\mailable.tpl.php';

        $this->generateClass($params);

        return 0;
    }
}
