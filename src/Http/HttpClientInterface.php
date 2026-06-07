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

namespace Myth\Postal\Http;

/**
 * The HTTP client seam used by API transports. A single blocking request
 * method keeps the contract small enough to drive with a test double; an
 * adapter may wrap a PSR-18 client.
 */
interface HttpClientInterface
{
    /**
     * Issues a request and returns the response. MUST throw on transport-level
     * failure (DNS, connection, timeout); a non-2xx status is a valid response
     * and MUST NOT throw.
     *
     * @param array<string, string> $headers
     */
    public function request(string $method, string $url, array $headers = [], string $body = ''): HttpResponse;
}
