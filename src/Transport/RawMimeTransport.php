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

/**
 * Marks a transport that delivers a raw RFC 5322 / MIME message verbatim and
 * honors Email::$rawMessage. Only such transports can carry a pre-signed
 * message, so DKIM signing is offered for these and refused for structured-API
 * transports (which sign server-side).
 */
interface RawMimeTransport extends TransportInterface
{
}
