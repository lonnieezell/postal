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

use RuntimeException;

/**
 * The default HTTP client, backed by ext-curl. Collects response headers into
 * a name => value map and throws on transport-level failures.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(private readonly int $timeout = 30)
    {
    }

    public function request(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $handle = curl_init();

        /** @var array<string, string> $responseHeaders */
        $responseHeaders = [];

        curl_setopt_array($handle, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $this->formatHeaders($headers),
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HEADERFUNCTION => static function ($_handle, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);

                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);

        $response = curl_exec($handle);

        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new RuntimeException('HTTP request failed: ' . $error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new HttpResponse($status, (string) $response, $responseHeaders);
    }

    /**
     * Flattens a name => value header map into the "Name: value" lines curl
     * expects.
     *
     * @param array<string, string> $headers
     *
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        return $lines;
    }
}
