<?php

declare(strict_types=1);

/**
 * This file is part of Myth/Postal.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Myth\Postal\Config;

use CodeIgniter\Config\BaseService;
use Myth\Postal\MailerManager;

class Services extends BaseService
{
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
