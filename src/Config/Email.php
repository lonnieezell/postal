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
use Myth\Postal\SuppressionListInterface;
use Myth\Postal\Transport\LogTransport;
use Myth\Postal\Transport\MailTransport;
use Myth\Postal\Transport\NullTransport;
use Myth\Postal\Transport\SendmailTransport;
use Myth\Postal\Transport\SesTransport;
use Myth\Postal\Transport\SmtpTransport;
use Myth\Postal\Transport\TransportInterface;
use Myth\Postal\UnsubscribeUrlInterface;

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
     * Whether to fire the email lifecycle events (composing, sending, sent,
     * failed) around each send. Set to false to suppress all of them.
     */
    public bool $fireEvents = true;

    /**
     * Optional class implementing SuppressionListInterface. When set, the
     * Mailer will instantiate this class and use it to filter suppressed
     * recipients before each send.
     *
     * @var class-string<SuppressionListInterface>|null
     */
    public ?string $suppressionList = null;

    /**
     * Optional class implementing UnsubscribeUrlInterface. When set, the
     * Mailer will auto-inject a List-Unsubscribe header for single-recipient
     * messages that don't already carry one.
     *
     * @var class-string<UnsubscribeUrlInterface>|null
     */
    public ?string $unsubscribeUrl = null;

    /**
     * Named mailers. Each entry selects a transport (by its key in $transports)
     * and carries that transport's settings.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $mailers = [
        'null' => ['transport' => 'null'],
        'log'  => ['transport' => 'log'],
    ];

    /**
     * The extensible transport-name => class map. Register a custom transport
     * by adding it here.
     *
     * @var array<string, class-string<TransportInterface>>
     */
    public array $transports = [
        'null'     => NullTransport::class,
        'log'      => LogTransport::class,
        'smtp'     => SmtpTransport::class,
        'sendmail' => SendmailTransport::class,
        'mail'     => MailTransport::class,
        'ses'      => SesTransport::class,
    ];
}
