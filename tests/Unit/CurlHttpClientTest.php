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
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Transport\CurlHttpClient;

/**
 * @internal
 */
final class CurlHttpClientTest extends CIUnitTestCase
{
    public function testConnectionFailureThrowsPostalException(): void
    {
        $this->expectException(PostalException::class);
        $this->expectExceptionMessage('HTTP request to http://127.0.0.1:1/emails failed');

        // Nothing listens on port 1, so the connection is refused immediately.
        (new CurlHttpClient())->request('POST', 'http://127.0.0.1:1/emails', ['Content-Type' => 'application/json'], '{}');
    }
}
