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

use Myth\Postal\Transport\MailFunction;

/**
 * A MailFunction double that captures the arguments handed to mail() so tests
 * can assert on the header/body split without sending real mail.
 */
final class FakeMailFunction implements MailFunction
{
    public ?string $to      = null;
    public ?string $subject = null;
    public ?string $message = null;
    public ?string $headers = null;
    public ?string $params  = null;

    public function __construct(private readonly bool $result = true)
    {
    }

    public function send(string $to, string $subject, string $message, string $headers, string $params): bool
    {
        $this->to      = $to;
        $this->subject = $subject;
        $this->message = $message;
        $this->headers = $headers;
        $this->params  = $params;

        return $this->result;
    }
}
