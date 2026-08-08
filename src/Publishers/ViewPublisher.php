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
 * Publishes the default layout and Mail Components into the application's
 * Views directory, where a host app can freely customize them. Discovered
 * and run by `php spark publish`.
 */
class ViewPublisher extends Publisher
{
    /**
     * Rooted at Views rather than Views/mail: the destination must already
     * exist for the base Publisher constructor to resolve it, and unlike
     * Views/mail, every host app already has a Views directory.
     *
     * @var string
     */
    protected $source = __DIR__ . '/../Views';

    /**
     * @var string
     */
    protected $destination = APPPATH . 'Views';

    /**
     * Copies the default layout and Mail Components, leaving an
     * already-published file untouched so a re-publish never discards the
     * application's customizations.
     */
    public function publish(): bool
    {
        return $this->addPath('mail', true)->merge(false);
    }
}
