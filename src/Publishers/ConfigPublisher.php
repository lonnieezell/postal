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

namespace Myth\Postal\Publishers;

use CodeIgniter\Publisher\Publisher;

/**
 * Publishes the mailer config stub into the application's Config directory,
 * where it overrides the package default. Discovered and run by
 * `php spark publish`.
 */
class ConfigPublisher extends Publisher
{
    /**
     * The stub lives outside src/ on purpose: it declares a class in the
     * application's Config namespace, so inside the package's PSR-4 root the
     * autoloader would pull it in — shadowing the application's own published
     * config, or fataling on a redeclaration.
     *
     * @var string
     */
    protected $source = __DIR__ . '/../../stubs';

    /**
     * @var string
     */
    protected $destination = APPPATH . 'Config';

    /**
     * Copies the stub, leaving an already-published file untouched so a
     * re-publish never discards the application's settings.
     */
    public function publish(): bool
    {
        return $this->addPath('Mailer.php', false)->copy(false);
    }
}
