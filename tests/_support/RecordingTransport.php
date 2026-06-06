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
use Myth\Postal\Transport\TransportInterface;

/**
 * A test transport that records every message it is handed and returns a
 * pre-seeded result, so adapter sends can be asserted without a live MTA.
 */
final class RecordingTransport implements TransportInterface
{
    /**
     * @var list<Email>
     */
    public array $sent = [];

    public function __construct(private readonly bool $succeed = true, private readonly ?string $error = null)
    {
    }

    public function send(Email $email): SendResult
    {
        $this->sent[] = $email;

        return $this->succeed ? SendResult::ok('test-id') : SendResult::fail($this->error ?? 'recording failure');
    }

    public function ping(): bool
    {
        return true;
    }
}
