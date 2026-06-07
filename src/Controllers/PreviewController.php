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

namespace Myth\Postal\Controllers;

use CodeIgniter\Controller;
use Myth\Postal\Config\Postal;
use Myth\Postal\Mailable;
use Myth\Postal\Previewable;
use Throwable;

/**
 * Serves the in-browser Mailable preview. Mounted by src/Config/Routes.php only
 * when the preview gate is open (non-production environment + enabled flag).
 */
class PreviewController extends Controller
{
    private readonly Postal $config;

    public function __construct(?Postal $config = null)
    {
        $this->config = $config ?? config(Postal::class);
    }

    /**
     * Lists every discovered Mailable with the subject from its preview
     * instance. A Mailable whose previewInstance()/build() throws is still
     * listed, carrying its error message, so one broken Mailable cannot blank
     * the whole list.
     */
    public function index(): string
    {
        $mailables = [];

        foreach ($this->discover() as $class) {
            $entry = ['class' => $class, 'subject' => null, 'error' => null];

            try {
                $entry['subject'] = $class::previewInstance()->render()->subject;
            } catch (Throwable $e) {
                $entry['error'] = $e->getMessage();
            }

            $mailables[] = $entry;
        }

        return view('Myth\Postal\Views\preview\index', [
            'mailables'   => $mailables,
            'previewPath' => $this->config->previewPath,
        ]);
    }

    /**
     * Scans the configured namespace/path pairs and returns the fully-qualified
     * names of every Mailable that implements Previewable.
     *
     * @return list<class-string<Mailable&Previewable>>
     */
    public function discover(): array
    {
        $found = [];

        foreach ($this->config->mailableNamespaces as $namespace => $path) {
            $namespace = rtrim($namespace, '\\');
            $files     = glob(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];

            foreach ($files as $file) {
                $class = $namespace . '\\' . basename($file, '.php');

                if (! class_exists($class)) {
                    continue;
                }

                if (is_subclass_of($class, Mailable::class) && is_a($class, Previewable::class, true)) {
                    $found[] = $class;
                }
            }
        }

        sort($found);

        return $found;
    }
}
