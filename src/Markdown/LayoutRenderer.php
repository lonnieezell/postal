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

use Myth\Postal\Config\Postal as PostalConfig;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

/**
 * Wraps already-converted content HTML in a Layout view (receiving it as
 * $content) and inlines the Layout's <style> block into element style=""
 * attributes for email-client compatibility.
 */
final readonly class LayoutRenderer
{
    public function __construct(private ?PostalConfig $config = null)
    {
    }

    /**
     * Wraps $content in $layoutView, falling back to
     * Config\Postal::$defaultLayout when no override is given, then runs
     * the composed HTML through CSS inlining.
     */
    public function render(string $content, ?string $layoutView = null): string
    {
        // Resolved by short name so that an application's Config\Postal, if
        // one has been published, is preferred over the package's default.
        $config = $this->config ?? config('Postal');

        $view = PackageView::resolve($layoutView ?? $config->defaultLayout);
        $html = view($view, ['content' => $content], ['debug' => false]);

        return (new CssToInlineStyles())->convert($html);
    }
}
