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

use Myth\Postal\Transport\HttpClientInterface;
use Myth\Postal\Transport\HttpResponse;
use Throwable;

/**
 * An HttpClientInterface double that captures the request handed to it and
 * returns a canned response (or throws a canned exception) so API transports
 * can be tested without a network.
 */
final class FakeHttpClient implements HttpClientInterface
{
    public ?string $method = null;
    public ?string $url    = null;

    /**
     * @var array<string, string>
     */
    public array $headers = [];

    public ?string $body = null;

    public function __construct(private readonly HttpResponse|Throwable $response = new HttpResponse(200, '{}'))
    {
    }

    public function request(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $this->method  = $method;
        $this->url     = $url;
        $this->headers = $headers;
        $this->body    = $body;

        if ($this->response instanceof Throwable) {
            throw $this->response;
        }

        return $this->response;
    }

    /**
     * Decodes the captured JSON request body.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        return json_decode((string) $this->body, true, 512, JSON_THROW_ON_ERROR);
    }
}
