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

namespace Myth\Postal\Preview;

use Myth\Postal\Attachment;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Mailable;
use Myth\Postal\MessageRenderer;
use Myth\Postal\Previewable;
use Throwable;

/**
 * Discovers and renders previewable Mailables, independent of any UI. Postal's
 * PreviewController uses it, and host applications can use it to build their
 * own preview screens.
 */
final readonly class MailablePreviewer
{
    /**
     * @param array<string, string> $namespaces Namespace => directory pairs to scan for Mailables
     */
    public function __construct(private array $namespaces)
    {
    }

    /**
     * Scans the namespace/path pairs and returns the fully-qualified names of
     * every Mailable that implements Previewable, sorted.
     *
     * @return list<class-string<Mailable&Previewable>>
     */
    public function discover(): array
    {
        $found = [];

        foreach ($this->namespaces as $namespace => $path) {
            $namespace = rtrim($namespace, '\\');
            $files     = glob(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '*.php');

            if ($files === false) {
                continue;
            }

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

    /**
     * Whether $class names an existing Mailable that implements Previewable.
     * Safe to call with untrusted input such as a URL segment.
     */
    public function isPreviewable(string $class): bool
    {
        // Validate the FQCN shape before class_exists() so a malformed segment
        // (e.g. one containing ".." path components) never reaches the autoloader.
        $identifier = '[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*';

        return preg_match('/^' . $identifier . '(\\\\' . $identifier . ')*$/', $class) === 1
            && class_exists($class)
            && is_subclass_of($class, Mailable::class)
            && is_a($class, Previewable::class, true);
    }

    /**
     * Builds the Mailable's preview instance and returns only its subject,
     * skipping the text and MIME rendering - cheap enough for a list screen.
     * Anything previewInstance()/build() throws is left to the caller.
     *
     * @throws PostalException When $class is not a previewable Mailable
     */
    public function subject(string $class): string
    {
        if (! $this->isPreviewable($class)) {
            throw PostalException::forUnknownPreviewable($class);
        }

        return $class::previewInstance()->render()->subject;
    }

    /**
     * Builds the Mailable's preview instance and renders it to subject, HTML,
     * plain text, and raw MIME. Anything thrown while doing so is returned in
     * the preview's $error rather than thrown, so one broken Mailable cannot
     * take down a whole preview screen.
     *
     * @throws PostalException When $class is not a previewable Mailable
     */
    public function render(string $class): MailablePreview
    {
        if (! $this->isPreviewable($class)) {
            throw PostalException::forUnknownPreviewable($class);
        }

        try {
            $email = $class::previewInstance()->render();

            $renderer = new MessageRenderer();
            $textAuto = $email->textBody === null && $email->htmlBody !== null;
            $text     = $email->textBody ?? ($email->htmlBody !== null ? $renderer->htmlToText($email->htmlBody) : '');

            return new MailablePreview(
                class: $class,
                subject: $email->subject,
                htmlBody: $email->htmlBody,
                text: $text,
                textAuto: $textAuto,
                rawMime: $renderer->render($email),
                attachments: array_map(static fn (Attachment $a): string => $a->name, $email->attachments),
            );
        } catch (Throwable $e) {
            return new MailablePreview(class: $class, error: $e);
        }
    }
}
