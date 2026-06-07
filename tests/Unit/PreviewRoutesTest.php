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

namespace Tests\Unit;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PreviewRoutesTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        Factories::reset('config');
    }

    /**
     * Requires the package routes file against a recording collection, so the
     * gate that wraps the route registration can be asserted directly.
     *
     * @return object{gets: array<string, string>, groupPrefix: string|null}
     */
    private function loadRoutes(bool $enablePreview): object
    {
        // Mutate the shared config the routes file resolves via config('Postal');
        // tearDown resets the config factory so this does not leak across tests.
        config('Postal')->enablePreview = $enablePreview;

        $routes = new class () {
            /**
             * @var array<string, string>
             */
            public array $gets = [];

            public ?string $groupPrefix = null;

            /**
             * @param array<string, mixed> $options
             */
            public function group(string $prefix, array $options, callable $callback): void
            {
                $this->groupPrefix = $prefix;
                $callback($this);
            }

            public function get(string $from, string $to): void
            {
                $this->gets[$from] = $to;
            }
        };

        require dirname(__DIR__, 2) . '/src/Config/Routes.php';

        return $routes;
    }

    public function testRoutesRegisteredWhenPreviewEnabled(): void
    {
        $routes = $this->loadRoutes(true);

        $this->assertSame('postal/preview', $routes->groupPrefix);
        $this->assertNotSame([], $routes->gets);
    }

    public function testNoRoutesRegisteredWhenPreviewDisabled(): void
    {
        $routes = $this->loadRoutes(false);

        $this->assertNull($routes->groupPrefix);
        $this->assertSame([], $routes->gets);
    }
}
