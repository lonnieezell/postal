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
use CodeIgniter\Exceptions\PageNotFoundException;
use Myth\Postal\Config\Postal;
use Myth\Postal\Mailable;
use Myth\Postal\MessageRenderer;
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
     * Renders a single Mailable's preview with HTML, plain-text, and raw-MIME
     * tabs. An unknown / non-previewable class is a 404; a Mailable that throws
     * while building has its error shown inline in the preview chrome.
     */
    public function show(string $class): string
    {
        $class = urldecode($class);

        if (! class_exists($class) || ! is_subclass_of($class, Mailable::class) || ! is_a($class, Previewable::class, true)) {
            throw PageNotFoundException::forPageNotFound('Unknown previewable Mailable: ' . $class);
        }

        try {
            $email = $class::previewInstance()->render();
        } catch (Throwable $e) {
            return view('Myth\Postal\Views\preview\show', [
                'class'       => $class,
                'previewPath' => $this->config->previewPath,
                'error'       => $e->getMessage() . "\n\n" . $e->getTraceAsString(),
            ]);
        }

        $renderer = new MessageRenderer();
        $textAuto = $email->textBody === null && $email->htmlBody !== null;
        $text     = $email->textBody ?? ($email->htmlBody !== null ? $renderer->htmlToText($email->htmlBody) : '');

        return view('Myth\Postal\Views\preview\show', [
            'class'       => $class,
            'previewPath' => $this->config->previewPath,
            'error'       => null,
            'subject'     => $email->subject,
            'htmlBody'    => $email->htmlBody,
            'text'        => $text,
            'textAuto'    => $textAuto,
            'rawMime'     => $renderer->render($email),
            'attachments' => array_map(static fn ($a): string => $a->name, $email->attachments),
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
