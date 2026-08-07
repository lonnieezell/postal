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

namespace Myth\Postal\Markdown;

use Stringable;

/**
 * The result of resolving a markdown view via the markdown() helper: the
 * already-converted HTML from that single resolution, plus a plain-text
 * fallback derived lazily from the same raw markdown source on demand.
 */
final class MarkdownString implements Stringable
{
    private ?string $text = null;

    public function __construct(
        private readonly string $markdown,
        private readonly string $html,
    ) {
    }

    public function __toString(): string
    {
        return $this->html;
    }

    /**
     * The plain-text fallback, derived from the raw markdown source rather
     * than from the converted HTML.
     */
    public function text(): string
    {
        return $this->text ??= service('markdown')->toText($this->markdown);
    }
}
