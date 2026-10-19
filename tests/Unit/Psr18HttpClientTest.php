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

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Transport\Psr18HttpClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
final class Psr18HttpClientTest extends CIUnitTestCase
{
    /**
     * @var array<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: list<mixed>}>
     */
    private array $history = [];

    public function testSendsTheRequestAndReturnsTheResponse(): void
    {
        $client = $this->client(new Response(422, ['X-Request-Id' => 'abc'], '{"message":"nope"}'));

        $response = $client->request('POST', 'https://api.example.com/emails', ['Authorization' => 'Bearer key'], '{"a":1}');

        $this->assertSame(422, $response->status);
        $this->assertSame('{"message":"nope"}', $response->body);
        $this->assertSame('abc', $response->headers['x-request-id']);

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('https://api.example.com/emails', (string) $request->getUri());
        $this->assertSame('Bearer key', $request->getHeaderLine('Authorization'));
        $this->assertSame('{"a":1}', (string) $request->getBody());
    }

    public function testClientExceptionBecomesPostalException(): void
    {
        $client = $this->client(new ConnectException('Connection refused', new Request('POST', 'https://api.example.com')));

        $this->expectException(PostalException::class);
        $this->expectExceptionMessage('Connection refused');

        $client->request('POST', 'https://api.example.com/emails');
    }

    private function client(ConnectException|Response $response): Psr18HttpClient
    {
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($this->history));

        $factory = new HttpFactory();

        return new Psr18HttpClient(new Client(['handler' => $stack, 'http_errors' => false]), $factory, $factory);
    }
}
