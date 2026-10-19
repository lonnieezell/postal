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
use Myth\Postal\Attachment;
use Myth\Postal\Email;
use Myth\Postal\Exceptions\PostalException;
use Psr\Log\LoggerInterface;

/**
 * Delivers a message through the Resend HTTP API (https://resend.com),
 * authenticating with the mailer's "key" setting.
 */
final readonly class ResendTransport extends AbstractApiTransport
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    /**
     * Resend tag names and values are restricted to ASCII letters, digits,
     * underscores and dashes (1–256 characters).
     */
    private const TAG_PATTERN = '/^[A-Za-z0-9_-]{1,256}$/';

    private string $key;

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings = [], ?HttpClientInterface $http = null, ?LoggerInterface $logger = null)
    {
        $this->key = (string) ($settings['key'] ?? '');

        if ($this->key === '') {
            throw PostalException::forMissingSetting('key', 'resend');
        }

        parent::__construct($http, $logger);
    }

    protected function endpoint(): string
    {
        return self::ENDPOINT;
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . $this->key];
    }

    protected function payload(Email $email): array
    {
        $payload = [
            'from'    => $email->from instanceof Address ? $this->formatAddress($email->from) : '',
            'to'      => array_map($this->formatAddress(...), $email->to),
            'subject' => $email->subject,
        ];

        foreach (['cc' => $email->cc, 'bcc' => $email->bcc] as $key => $list) {
            if ($list !== []) {
                $payload[$key] = array_map($this->formatAddress(...), $list);
            }
        }

        if ($email->replyTo instanceof Address) {
            $payload['reply_to'] = [$this->formatAddress($email->replyTo)];
        }

        if ($email->htmlBody !== null) {
            $payload['html'] = $email->htmlBody;
        }

        if ($email->textBody !== null) {
            $payload['text'] = $email->textBody;
        }

        if ($email->headers !== []) {
            $payload['headers'] = $email->headers;
        }

        if ($email->attachments !== []) {
            $payload['attachments'] = array_map($this->attachment(...), $email->attachments);
        }

        $tags = [];

        foreach ($this->supportedMetadata($email, self::TAG_PATTERN, 'Resend tags allow only [A-Za-z0-9_-] (1-256 chars)') as $name => $value) {
            $tags[] = ['name' => $name, 'value' => $value];
        }

        if ($tags !== []) {
            $payload['tags'] = $tags;
        }

        if ($email->returnPath !== null) {
            $this->dropUnsupported('the return path', 'Resend manages the bounce address itself');
        }

        if ($email->priority !== 3) {
            $this->dropUnsupported('the priority', 'Resend has no priority setting');
        }

        return $payload;
    }

    protected function messageId(array $data): ?string
    {
        return isset($data['id']) && is_string($data['id']) ? $data['id'] : null;
    }

    protected function errorMessage(array $data): ?string
    {
        return isset($data['message']) && is_string($data['message']) ? $data['message'] : null;
    }

    /**
     * Maps an attachment onto Resend's attachment object; an inline image
     * carries its Content-ID so the HTML's "cid:" reference resolves.
     *
     * @return array<string, string>
     */
    private function attachment(Attachment $attachment): array
    {
        $mapped = [
            'content'      => base64_encode($attachment->content()),
            'filename'     => $attachment->name,
            'content_type' => $attachment->mimeType(),
        ];

        if ($attachment->cid !== null) {
            $mapped['content_id'] = $attachment->cid;
        }

        return $mapped;
    }
}
