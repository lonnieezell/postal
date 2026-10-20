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
 * The minimal result of an HTTP request made through HttpClientInterface.
 */
final readonly class HttpResponse
{
    /**
     * @param int    $status the HTTP status code
     * @param string $body   the raw response body
     */
    public function __construct(
        public int $status,
        public string $body = '',
    ) {
    }
}
