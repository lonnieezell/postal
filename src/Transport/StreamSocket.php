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
 * The shipped SmtpSocket implementation: a real TCP/TLS stream to an SMTP
 * server, opened with the PHP streams API.
 */
final class StreamSocket implements SmtpSocket
{
    /**
     * @var resource|null
     */
    private $stream;

    public function connect(string $host, int $port, int $timeout): void
    {
        $errno  = 0;
        $errstr = '';

        $stream = @stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
        );

        if ($stream === false) {
            throw SmtpException::forConnectionFailure($host, $port, $errstr !== '' ? $errstr : 'unknown error');
        }

        $this->stream = $stream;
        stream_set_timeout($this->stream, $timeout);
    }

    public function write(string $data): void
    {
        if ($this->stream === null) {
            throw SmtpException::forBrokenPipe('write to a closed connection');
        }

        if (@fwrite($this->stream, $data) === false) {
            throw SmtpException::forBrokenPipe('write failed');
        }
    }

    public function readLine(): string
    {
        if ($this->stream === null) {
            throw SmtpException::forBrokenPipe('read from a closed connection');
        }

        $line = @fgets($this->stream);

        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);

            throw SmtpException::forBrokenPipe($meta['timed_out'] ? 'read timed out' : 'connection closed by peer');
        }

        return $line;
    }

    public function enableCrypto(bool $enable, int $method): bool
    {
        if ($this->stream === null) {
            return false;
        }

        return (bool) @stream_socket_enable_crypto($this->stream, $enable, $method);
    }

    public function isConnected(): bool
    {
        return $this->stream !== null;
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            @fclose($this->stream);
            $this->stream = null;
        }
    }
}
