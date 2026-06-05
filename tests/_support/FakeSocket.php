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

use Myth\Postal\Exceptions\SmtpException;
use Myth\Postal\Transport\SmtpSocket;

/**
 * A scripted SmtpSocket double for unit-testing the SMTP conversation without a
 * real server. It replays a queue of server reply lines and records everything
 * the transport writes so tests can assert on the protocol exchange.
 */
final class FakeSocket implements SmtpSocket
{
    /**
     * Every chunk written by the transport, in order.
     *
     * @var list<string>
     */
    public array $writes = [];

    /**
     * Each connect() call recorded as [host, port, timeout].
     *
     * @var list<array{0: string, 1: int, 2: int}>
     */
    public array $connections = [];

    /**
     * Each enableCrypto() call recorded as [enable, method].
     *
     * @var list<array{0: bool, 1: int}>
     */
    public array $cryptoCalls = [];

    private bool $connected = false;

    /**
     * @param list<string> $script server reply lines, each ending in CRLF
     */
    public function __construct(
        /**
         * Server reply lines to hand back from readLine(), in order. Each entry is
         * one line and should include its trailing CRLF.
         */
        private array $script = [],
        private readonly bool $cryptoResult = true,
    ) {
    }

    public function connect(string $host, int $port, int $timeout): void
    {
        $this->connections[] = [$host, $port, $timeout];
        $this->connected     = true;
    }

    public function write(string $data): void
    {
        if (! $this->connected) {
            throw SmtpException::forBrokenPipe('write to a closed FakeSocket');
        }

        $this->writes[] = $data;
    }

    public function readLine(): string
    {
        if ($this->script === []) {
            throw SmtpException::forBrokenPipe('FakeSocket script exhausted');
        }

        return array_shift($this->script);
    }

    public function enableCrypto(bool $enable, int $method): bool
    {
        $this->cryptoCalls[] = [$enable, $method];

        return $this->cryptoResult;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    /**
     * The full transcript of everything the transport wrote, joined.
     */
    public function transcript(): string
    {
        return implode('', $this->writes);
    }
}
