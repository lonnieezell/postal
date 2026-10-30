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

namespace Tests\Support;

use Myth\Postal\Config\Mailer;

/**
 * Stands in for an application's published Config\Mailer. Unlike requiring
 * stubs/Mailer.php, selecting this via Factories::define() can be undone
 * between tests.
 */
final class AppMailerConfig extends Mailer
{
}
