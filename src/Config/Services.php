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

namespace Myth\Postal\Config;

use CodeIgniter\Config\BaseService;
use Config\Email as LegacyEmailConfig;
use Myth\Postal\LegacyEmailAdapter;
use Myth\Postal\MailerManager;

class Services extends BaseService
{
    /**
     * A drop-in replacement for the framework's email service. Returns the
     * legacy adapter so existing service('email') code keeps working on top of
     * the new mailer.
     *
     * Note: the shared instance is cached directly in the service registry
     * rather than through getSharedInstance(), which would construct via the
     * framework's email service and bypass this override.
     *
     * @param array<string, mixed>|LegacyEmailConfig|null $config
     */
    public static function email($config = null, bool $getShared = true): LegacyEmailAdapter
    {
        if ($getShared) {
            if (! (static::$instances['email'] ?? null) instanceof LegacyEmailAdapter) {
                static::$instances['email'] = static::email($config, false);
            }

            return static::$instances['email'];
        }

        $adapter = $config instanceof LegacyEmailConfig
            ? new LegacyEmailAdapter($config)
            : new LegacyEmailAdapter();

        if (is_array($config)) {
            $adapter->initialize($config);
        }

        return $adapter;
    }

    /**
     * The Postal mailer manager, entry point for composing and sending mail.
     */
    public static function mailer(bool $getShared = true): MailerManager
    {
        if ($getShared) {
            return static::getSharedInstance('mailer');
        }

        return new MailerManager();
    }
}
