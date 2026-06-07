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

use Myth\Postal\Email;
use Myth\Postal\SendResult;
use Myth\Postal\Transport\RawMimeTransport;

/**
 * A raw-MIME transport double that records the (already rendered) message it is
 * handed, so a DkimSigningTransport's signed output can be inspected without a
 * live MTA.
 */
final class RawRecordingTransport implements RawMimeTransport
{
    public ?Email $lastEmail = null;

    public function send(Email $email): SendResult
    {
        $this->lastEmail = $email;

        return SendResult::ok('test-id');
    }

    public function ping(): bool
    {
        return true;
    }
}
