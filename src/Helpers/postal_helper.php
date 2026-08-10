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

use Myth\Postal\Markdown\MarkdownString;

if (! function_exists('markdown')) {
    /**
     * Resolves $view through CI4's view() to raw markdown source, converts it
     * via service('markdown') in the same call, and returns a MarkdownString
     * wrapping both outputs. Autoloaded via Composer's "files" mechanism so
     * it's always available, matching the always-on ergonomics of view().
     */
    function markdown(string $view, array $data = []): MarkdownString
    {
        // Debug Toolbar view-tracing comments would otherwise be parsed as
        // part of the markdown source and leak into the rendered message.
        $markdown = view($view, $data, ['debug' => false]);

        return new MarkdownString($markdown, service('markdown')->toHtml($markdown));
    }
}
