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

use Myth\Postal\Config\Postal;

/**
 * Auto-discovered by CodeIgniter in every host app, including production, so the
 * preview routes are registered only when the gate is open: a non-production
 * environment AND Config\Postal::$enablePreview enabled.
 *
 * @var CodeIgniter\Router\RouteCollection $routes
 */
$postal = config(Postal::class);

if ($postal->previewEnabled(ENVIRONMENT)) {
    $routes->group($postal->previewPath, ['namespace' => 'Myth\Postal\Controllers'], static function ($routes): void {
        $routes->get('/', 'PreviewController::index');
        $routes->get('(:segment)', 'PreviewController::show/$1');
    });
}
