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
     * Seconds to wait for the whole request before giving up.
     */
    private const TIMEOUT = 30;

    public function request(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $responseHeaders = [];

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
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                if (str_contains($line, ':')) {
                    [$name, $value]                           = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }

                return strlen($line);
            },
        ]);

        $responseBody = curl_exec($handle);

        if (! is_string($responseBody)) {
            throw new PostalException(sprintf('HTTP request to %s failed: %s', $url, curl_error($handle)));
        }

        return new HttpResponse(curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $responseBody, $responseHeaders);
    }
}
