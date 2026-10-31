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
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Mailable;
use Myth\Postal\Preview\MailablePreviewer;
use Myth\Postal\Previewable;
use Throwable;

/**
 * Serves the in-browser Mailable preview. Mounted by src/Config/Routes.php only
 * when the preview gate is open (non-production environment + enabled flag).
 */
final class PreviewController extends Controller
{
    private readonly Postal $config;
    private readonly MailablePreviewer $previewer;

    public function __construct(?Postal $config = null)
    {
        $this->config    = $config ?? config('Postal');
        $this->previewer = new MailablePreviewer($this->config->mailableNamespaces);
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
                $entry['subject'] = $this->previewer->subject($class);
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
     * while building has its error shown inline in the preview chrome. The
     * index links write the class with dots for backslashes, because
     * CodeIgniter's default permittedURIChars rejects a backslash.
     */
    public function show(string $class): string
    {
        $class = str_replace('.', '\\', rawurldecode($class));

        try {
            $preview = $this->previewer->render($class);
        } catch (PostalException $e) {
            throw PageNotFoundException::forPageNotFound($e->getMessage());
        }

        if ($preview->error !== null) {
            return view('Myth\Postal\Views\preview\show', [
                'class'       => $class,
                'previewPath' => $this->config->previewPath,
                'error'       => $preview->error->getMessage() . "\n\n" . $preview->error->getTraceAsString(),
            ]);
        }

        return view('Myth\Postal\Views\preview\show', [
            'class'       => $class,
            'previewPath' => $this->config->previewPath,
            'error'       => null,
            'subject'     => $preview->subject,
            'htmlBody'    => $preview->htmlBody,
            'text'        => $preview->text,
            'textAuto'    => $preview->textAuto,
            'rawMime'     => $preview->rawMime,
            'attachments' => $preview->attachments,
        ]);
    }

    /**
     * Returns the fully-qualified names of every previewable Mailable in the
     * configured namespace/path pairs.
     *
     * @return list<class-string<Mailable&Previewable>>
     */
    public function discover(): array
    {
        return $this->previewer->discover();
    }
}
