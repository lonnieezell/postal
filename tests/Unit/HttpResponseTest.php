<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Http\HttpResponse;

/**
 * @internal
 */
final class HttpResponseTest extends CIUnitTestCase
{
    public function testExposesStatusBodyAndHeaders(): void
    {
        $response = new HttpResponse(202, '{"MessageId":"abc"}', ['Content-Type' => 'application/json']);

        $this->assertSame(202, $response->status);
        $this->assertSame('{"MessageId":"abc"}', $response->body);
        $this->assertSame(['Content-Type' => 'application/json'], $response->headers);
    }

    public function testHeadersDefaultToEmpty(): void
    {
        $response = new HttpResponse(200, '');

        $this->assertSame([], $response->headers);
    }
}
