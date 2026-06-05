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
 * The shipped MailFunction implementation that calls PHP's native mail().
 */
final class NativeMailFunction implements MailFunction
{
    public function send(string $to, string $subject, string $message, string $headers, string $params): bool
    {
        // The fifth argument is omitted when empty: passing "" as additional
        // parameters is rejected by some SAPIs.
        return $params === ''
            ? mail($to, $subject, $message, $headers)
            : mail($to, $subject, $message, $headers, $params);
    }
}
