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

use CodeIgniter\Config\BaseConfig;
use Myth\Postal\Transport\NullTransport;
use Myth\Postal\Transport\TransportInterface;

/**
 * Postal mailer configuration.
 */
class Email extends BaseConfig
{
    /**
     * The name of the mailer used when none is named explicitly.
     */
    public string $default = 'null';

    /**
     * Named mailers. Each entry selects a transport (by its key in $transports)
     * and carries that transport's settings.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $mailers = [
        'null' => ['transport' => 'null'],
    ];

    /**
     * The extensible transport-name => class map. Register a custom transport
     * by adding it here.
     *
     * @var array<string, class-string<TransportInterface>>
     */
    public array $transports = [
        'null' => NullTransport::class,
    ];
}
