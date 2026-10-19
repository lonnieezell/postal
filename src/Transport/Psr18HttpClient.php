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
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Adapts any PSR-18 HTTP client (with PSR-17 factories) to the
 * HttpClientInterface seam, for applications that already ship one.
 */
final readonly class Psr18HttpClient implements HttpClientInterface
{
    public function __construct(
        private ClientInterface $client,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function request(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $request = $this->requestFactory->createRequest($method, $url)
            ->withBody($this->streamFactory->createStream($body));

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new PostalException(sprintf('HTTP request to %s failed: %s', $url, $e->getMessage()), 0, $e);
        }

        $responseHeaders = [];

        foreach (array_keys($response->getHeaders()) as $name) {
            $responseHeaders[strtolower((string) $name)] = $response->getHeaderLine((string) $name);
        }

        return new HttpResponse($response->getStatusCode(), (string) $response->getBody(), $responseHeaders);
    }
}
