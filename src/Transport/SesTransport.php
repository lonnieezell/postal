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

use Aws\Exception\AwsException;
use Aws\SesV2\SesV2Client;
use Myth\Postal\Address;
use Myth\Postal\Email;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\MessageRenderer;
use Myth\Postal\SendResult;
use Psr\Log\LoggerInterface;

/**
 * Delivers a message through Amazon SES using the official aws/aws-sdk-php
 * SesV2 client (which signs requests with SigV4 and owns the HTTP transport).
 * Sends structured "Simple" content by default and switches to raw MIME when
 * the message carries attachments/inline images or raw is forced.
 */
final class SesTransport implements TransportInterface
{
    private const CHARSET = 'UTF-8';

    /**
     * SES EmailTag names and values are restricted to ASCII letters, digits,
     * underscores and dashes (1–256 characters); anything else is dropped.
     */
    private const TAG_PATTERN = '/^[A-Za-z0-9_-]{1,256}$/';

    private readonly SesV2Client $client;
    private readonly LoggerInterface $logger;
    private readonly ?string $configurationSet;
    private readonly bool $forceRaw;

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings = [], ?SesV2Client $client = null, ?LoggerInterface $logger = null)
    {
        $this->client           = $client ?? $this->makeClient($settings);
        $this->logger           = $logger ?? service('logger');
        $this->configurationSet = isset($settings['configurationSet']) ? (string) $settings['configurationSet'] : null;
        $this->forceRaw         = (bool) ($settings['forceRaw'] ?? false);
    }

    public function send(Email $email): SendResult
    {
        try {
            $result = $this->client->sendEmail($this->payload($email));
        } catch (AwsException $e) {
            return SendResult::fail($e->getAwsErrorMessage() ?? $e->getMessage(), $e);
        }

        $messageId = $result['MessageId'] ?? null;

        return SendResult::ok(is_string($messageId) ? $messageId : null, $result->toArray());
    }

    public function ping(): bool
    {
        return true;
    }

    /**
     * Builds the SesV2 SendEmail request parameters for the message.
     *
     * @return array<string, mixed>
     */
    private function payload(Email $email): array
    {
        $payload = [
            'FromEmailAddress' => $email->from instanceof Address ? $this->formatAddress($email->from) : '',
            'Destination'      => $this->destination($email),
            'Content'          => $this->content($email),
        ];

        if ($email->replyTo instanceof Address) {
            $payload['ReplyToAddresses'] = [$this->formatAddress($email->replyTo)];
        }

        $tags = $this->emailTags($email);

        if ($tags !== []) {
            $payload['EmailTags'] = $tags;
        }

        if ($this->configurationSet !== null && $this->configurationSet !== '') {
            $payload['ConfigurationSetName'] = $this->configurationSet;
        }

        return $payload;
    }

    /**
     * Builds the SES Destination block, omitting empty recipient lists.
     *
     * @return array<string, list<string>>
     */
    private function destination(Email $email): array
    {
        $destination = [];

        foreach (['ToAddresses' => $email->to, 'CcAddresses' => $email->cc, 'BccAddresses' => $email->bcc] as $key => $list) {
            if ($list !== []) {
                $destination[$key] = array_map($this->formatAddress(...), $list);
            }
        }

        return $destination;
    }

    /**
     * Chooses raw MIME when attachments/inline parts are present or raw is
     * forced, otherwise structured Simple content.
     *
     * @return array<string, mixed>
     */
    private function content(Email $email): array
    {
        // Raw mode for attachments/inline parts, when forced, and for HTML
        // without an explicit text part: only the renderer can synthesise the
        // plain-text alternative that keeps HTML mail off the bare-HTML spam
        // signal, which Simple content cannot reproduce.
        $htmlNeedsFallback = $email->htmlBody !== null && $email->textBody === null;

        if ($this->forceRaw || $email->attachments !== [] || $htmlNeedsFallback) {
            return ['Raw' => ['Data' => (new MessageRenderer())->render($email)]];
        }

        $body = [];

        if ($email->textBody !== null) {
            $body['Text'] = ['Data' => $email->textBody, 'Charset' => self::CHARSET];
        }

        if ($email->htmlBody !== null) {
            $body['Html'] = ['Data' => $email->htmlBody, 'Charset' => self::CHARSET];
        }

        // SES requires at least one body part.
        if ($body === []) {
            $body['Text'] = ['Data' => '', 'Charset' => self::CHARSET];
        }

        return [
            'Simple' => [
                'Subject' => ['Data' => $email->subject, 'Charset' => self::CHARSET],
                'Body'    => $body,
            ],
        ];
    }

    /**
     * Maps the message metadata onto SES EmailTags, dropping and debug-logging
     * any key or value that violates the SES tag character set.
     *
     * @return list<array{Name: string, Value: string}>
     */
    private function emailTags(Email $email): array
    {
        $tags = [];

        foreach ($email->metadata as $key => $value) {
            if (preg_match(self::TAG_PATTERN, $key) !== 1 || preg_match(self::TAG_PATTERN, $value) !== 1) {
                $this->logger->debug(sprintf(
                    'SesTransport dropped metadata "%s": SES EmailTags allow only [A-Za-z0-9_-] (1-256 chars).',
                    $key,
                ));

                continue;
            }

            $tags[] = ['Name' => $key, 'Value' => $value];
        }

        return $tags;
    }

    /**
     * Formats an address as "Name <email>" (display name quoted) or the bare
     * address when no name is set.
     */
    private function formatAddress(Address $address): string
    {
        if ($address->name === '') {
            return $address->email;
        }

        // A non-ASCII display name must be RFC 2047-encoded; a pure-ASCII name is
        // wrapped in a quoted-string so specials (e.g. a comma) cannot split it.
        $name = preg_match('/[^\x20-\x7E]/', $address->name) === 1
            ? mb_encode_mimeheader($address->name, self::CHARSET, 'Q')
            : '"' . addcslashes($address->name, '"\\') . '"';

        return $name . ' <' . $address->email . '>';
    }

    /**
     * Builds the SesV2 client from settings, falling back to the AWS default
     * credential provider chain when no key/secret are configured.
     *
     * @param array<string, mixed> $settings
     */
    private function makeClient(array $settings): SesV2Client
    {
        if (! class_exists(SesV2Client::class)) {
            throw PostalException::forMissingPackage('aws/aws-sdk-php', 'ses');
        }

        $config = [
            'version' => 'latest',
            'region'  => (string) ($settings['region'] ?? 'us-east-1'),
        ];

        if (isset($settings['key'], $settings['secret'])) {
            $config['credentials'] = [
                'key'    => (string) $settings['key'],
                'secret' => (string) $settings['secret'],
            ];

            if (isset($settings['token'])) {
                $config['credentials']['token'] = (string) $settings['token'];
            }
        }

        return new SesV2Client($config);
    }
}
