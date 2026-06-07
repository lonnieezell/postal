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

use Myth\Postal\Http\HttpClientInterface;
use Myth\Postal\Http\HttpResponse;
use RuntimeException;

/**
 * A scripted HttpClientInterface double for unit-testing API transports without
 * making real network calls. It records the request it received and returns a
 * canned response, or throws a canned transport error.
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

    private readonly HttpResponse $response;
    private readonly ?RuntimeException $error;

    public function __construct(?HttpResponse $response = null, ?RuntimeException $error = null)
    {
        $this->response = $response ?? new HttpResponse(200, '');
        $this->error    = $error;
    }

    public function request(string $method, string $url, array $headers = [], string $body = ''): HttpResponse
    {
        $this->method  = $method;
        $this->url     = $url;
        $this->headers = $headers;
        $this->body    = $body;

        if ($this->error instanceof RuntimeException) {
            throw $this->error;
        }

        return $this->response;
    }
}
