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

use Myth\Postal\Mailer;
use Myth\Postal\MailerManager;
use Myth\Postal\Transport\TransportInterface;

/**
 * A MailerManager double that records the requested mailer name and always
 * returns a Mailer backed by the given transport (events disabled), so command
 * sends can be asserted without resolving real config.
 */
final class RecordingMailerManager extends MailerManager
{
    public ?string $requestedName = null;

    public function __construct(private readonly TransportInterface $boundTransport)
    {
    }

    public function mailer(?string $name = null): Mailer
    {
        $this->requestedName = $name;

        return new Mailer($this->boundTransport, false);
    }
}
