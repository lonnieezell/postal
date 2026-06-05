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

use Myth\Postal\Exceptions\SmtpException;

/**
 * The thin socket seam the SMTP transport speaks through. Abstracting the wire
 * lets the protocol/state machine be unit-tested with a scripted double while
 * the shipped StreamSocket talks to a real server.
 */
interface SmtpSocket
{
    /**
     * Opens the connection. The host may carry a stream scheme (e.g. "ssl://")
     * for an implicit-TLS connection.
     *
     * @throws SmtpException when the connection cannot be established
     */
    public function connect(string $host, int $port, int $timeout): void;

    /**
     * Writes raw bytes to the connection.
     *
     * @throws SmtpException when the write fails
     */
    public function write(string $data): void;

    /**
     * Reads a single CRLF-terminated line, including the trailing CRLF.
     *
     * @throws SmtpException when the read fails or the peer closed the stream
     */
    public function readLine(): string;

    /**
     * Toggles TLS on the live connection for a STARTTLS upgrade. The method is
     * one of the STREAM_CRYPTO_METHOD_* constants.
     */
    public function enableCrypto(bool $enable, int $method): bool;

    /**
     * Returns true while the connection is open.
     */
    public function isConnected(): bool;

    /**
     * Closes the connection if open.
     */
    public function close(): void;
}
