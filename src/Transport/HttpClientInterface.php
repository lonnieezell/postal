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

use Myth\Postal\Exceptions\PostalException;

/**
 * The HTTP seam API transports send their requests through, so they can be
 * tested without a network.
 */
interface HttpClientInterface
{
    /**
     * Performs a request and returns the response, whatever its status code.
     *
     * @param array<string, string> $headers
     *
     * @throws PostalException when no response could be obtained (e.g. a connection failure)
     */
    public function request(string $method, string $url, array $headers = [], string $body = ''): HttpResponse;
}
