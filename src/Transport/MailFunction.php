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

namespace Myth\Postal\Transport;

/**
 * The seam over PHP's native mail() function. Abstracting the call lets the mail
 * transport be unit-tested with a double while the shipped NativeMailFunction
 * invokes the real function.
 */
interface MailFunction
{
    /**
     * Sends a message. The signature mirrors PHP's mail(): $headers carries the
     * additional headers and $params the additional command-line arguments (an
     * empty string when none apply).
     */
    public function send(string $to, string $subject, string $message, string $headers, string $params): bool;
}
