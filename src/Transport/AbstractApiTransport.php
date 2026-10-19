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

use Myth\Postal\Address;
use Myth\Postal\Email;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\SendResult;
use Psr\Log\LoggerInterface;
use ReflectionClass;

/**
 * Shared plumbing for transports that deliver through a provider's HTTP JSON
 * API: issues the request through the HttpClientInterface seam, encodes the
 * provider payload as JSON and translates the response into a SendResult.
 * Subclasses supply only the provider's endpoint, auth and payload mapping.
 */
abstract readonly class AbstractApiTransport implements TransportInterface
{
    protected HttpClientInterface $http;
    protected LoggerInterface $logger;

    public function __construct(?HttpClientInterface $http = null, ?LoggerInterface $logger = null)
    {
        $this->http   = $http ?? new CurlHttpClient();
        $this->logger = $logger ?? service('logger');
    }

    public function send(Email $email): SendResult
    {
        try {
            $response = $this->http->request(
                'POST',
                $this->endpoint(),
                $this->headers() + ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
                json_encode($this->payload($email), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            );
        } catch (PostalException $e) {
            return SendResult::fail($e->getMessage(), $e);
        }

        $data = json_decode($response->body, true);
        $data = is_array($data) ? $data : [];

        if ($response->status < 200 || $response->status >= 300) {
            return SendResult::fail($this->errorMessage($data) ?? 'HTTP ' . $response->status, $data);
        }

        return SendResult::ok($this->messageId($data), $data);
    }

    public function ping(): bool
    {
        return true;
    }

    /**
     * The URL the message is POSTed to.
     */
    abstract protected function endpoint(): string;

    /**
     * Provider-specific request headers, typically authentication.
     *
     * @return array<string, string>
     */
    abstract protected function headers(): array;

    /**
     * Maps the message onto the provider's JSON request body.
     *
     * @return array<string, mixed>
     */
    abstract protected function payload(Email $email): array;

    /**
     * Extracts the provider message id from a decoded success response.
     *
     * @param array<array-key, mixed> $data
     */
    abstract protected function messageId(array $data): ?string;

    /**
     * Extracts the provider's error message from a decoded error response, or
     * null to fall back to the HTTP status.
     *
     * @param array<array-key, mixed> $data
     */
    abstract protected function errorMessage(array $data): ?string;

    /**
     * Returns the message metadata whose key and value both match the
     * provider's tag pattern, dropping and debug-logging the rest.
     *
     * @return array<string, string>
     */
    protected function supportedMetadata(Email $email, string $pattern, string $rule): array
    {
        $metadata = [];

        foreach ($email->metadata as $key => $value) {
            if (preg_match($pattern, $key) !== 1 || preg_match($pattern, $value) !== 1) {
                // Log the key only, never the value: it may carry personal data.
                $this->dropUnsupported(sprintf('metadata "%s"', $key), $rule);

                continue;
            }

            $metadata[$key] = $value;
        }

        return $metadata;
    }

    /**
     * Records that a message feature the provider cannot carry was dropped.
     * Unsupported features never fail the send.
     */
    protected function dropUnsupported(string $feature, string $reason): void
    {
        $this->logger->debug(sprintf('%s dropped %s: %s.', (new ReflectionClass($this))->getShortName(), $feature, $reason));
    }

    /**
     * Formats an address as "Name <email>" (display name quoted, or RFC
     * 2047-encoded when non-ASCII) or the bare address when no name is set.
     */
    protected function formatAddress(Address $address): string
    {
        if ($address->name === '') {
            return $address->email;
        }

        $name = preg_match('/[^\x20-\x7E]/', $address->name) === 1
            ? mb_encode_mimeheader($address->name, 'UTF-8', 'Q')
            : '"' . addcslashes($address->name, '"\\') . '"';

        return $name . ' <' . $address->email . '>';
    }
}
