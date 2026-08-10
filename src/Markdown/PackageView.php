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

/**
 * Resolves a bare view path (e.g. "mail/components/button") the way an
 * application-published or fully custom view would resolve, falling back to
 * this package's own bundled copy under the Myth\Postal\Views namespace
 * when nothing is found. CodeIgniter's FileLocator only checks APPPATH for
 * bare (non-namespaced) view paths, so without this fallback the package's
 * own shipped views (e.g. src/Views/mail/components/button.php) would be
 * unreachable until a host application publishes them.
 */
final class PackageView
{
    private function __construct()
    {
    }

    public static function resolve(string $view): string
    {
        if (service('locator')->locateFile($view, 'Views') !== false) {
            return $view;
        }

        return 'Myth\\Postal\\Views\\' . str_replace('/', '\\', $view);
    }
}
