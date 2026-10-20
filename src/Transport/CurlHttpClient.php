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
 * The default HttpClientInterface implementation, built on ext-curl.
 */
final class CurlHttpClient implements HttpClientInterface
{
    /**
     * Seconds to wait for the connection to open before giving up.
     */
    private const CONNECT_TIMEOUT = 10;

    /**
     * Seconds to wait for the whole request before giving up.
     */
    private const TIMEOUT = 30;

    public function request(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        if (! function_exists('curl_init')) {
            throw new PostalException('CurlHttpClient requires the PHP "curl" extension. Install it, or override service(\'postalHttpClient\') with a Psr18HttpClient.');
        }

        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        $handle = curl_init($url);

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
        ]);

        $responseBody = curl_exec($handle);

        if (! is_string($responseBody)) {
            throw new PostalException(sprintf('HTTP request to %s failed: %s', $url, curl_error($handle)));
        }

        return new HttpResponse(curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $responseBody);
    }
}
