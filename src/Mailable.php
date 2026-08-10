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

namespace Myth\Postal;

use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Markdown\LayoutRenderer;

/**
 * A reusable, class-based email definition. Subclasses compose the message in
 * build(), which runs lazily at send time. Sending routes through the bound
 * mailer service and tags the message with the mailable's class name so tests
 * can assert on it by type.
 */
abstract class Mailable
{
    protected Email $email;

    /**
     * The named mailer to route through, or null for the default mailer.
     */
    protected ?string $mailerName = null;

    /**
     * The Layout view markdown() wraps its converted HTML in, or null to
     * fall back to Config\Postal::$defaultLayout.
     */
    protected ?string $layoutView = null;

    /**
     * Whether markdown() has already resolved $layoutView into rendered
     * HTML, so a later layout() call would silently have no effect.
     */
    private bool $markdownRendered = false;

    public function __construct()
    {
        $this->email = new Email();
    }

    /**
     * Composes the message. Called once, lazily, when send() runs.
     */
    abstract protected function build(): void;

    /**
     * Runs build() and returns the composed Email without sending it. The
     * build-without-send seam used by the preview tooling.
     */
    public function render(): Email
    {
        $this->build();

        return $this->email;
    }

    /**
     * Builds the message (if not already built) and sends it through the
     * configured mailer, tagging the message with this mailable's class.
     */
    public function send(): SendResult
    {
        $this->build();

        $this->email->mailableClass = static::class;

        return service('mailer')->mailer($this->mailerName)->send($this->email);
    }

    protected function from(string $address, string $name = ''): static
    {
        $this->email->from($address, $name);

        return $this;
    }

    /**
     * @param list<string>|string $address
     */
    protected function to(array|string $address, string $name = ''): static
    {
        $this->email->to($address, $name);

        return $this;
    }

    protected function subject(string $subject): static
    {
        $this->email->subject($subject);

        return $this;
    }

    protected function html(string $html): static
    {
        $this->email->html($html);

        return $this;
    }

    protected function text(string $text): static
    {
        $this->email->text($text);

        return $this;
    }

    /**
     * Selects the named mailer to send through (as configured in Config\Mailer).
     */
    protected function transport(string $name): static
    {
        $this->mailerName = $name;

        return $this;
    }

    /**
     * Overrides the Layout view used by markdown() for this Mailable,
     * instead of Config\Postal::$defaultLayout. Must be called before
     * markdown(), which resolves the layout as soon as it runs.
     */
    protected function layout(string $view): static
    {
        if ($this->markdownRendered) {
            throw PostalException::forLayoutAfterMarkdown();
        }

        $this->layoutView = $view;

        return $this;
    }

    /**
     * Resolves and converts $view via the markdown() helper, wraps the
     * resulting HTML in the resolved Layout with CSS inlined, and sets both
     * outputs onto the Email: html() with the rendered HTML, text() with
     * the plain-text fallback derived from the same raw markdown source.
     *
     * @param array<string, mixed> $data
     */
    protected function markdown(string $view, array $data = []): static
    {
        $this->markdownRendered = true;

        $rendered = markdown($view, $data);
        $html     = (new LayoutRenderer())->render((string) $rendered, $this->layoutView);

        return $this->html($html)->text($rendered->text());
    }
}
