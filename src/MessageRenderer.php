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

namespace Myth\Postal;

/**
 * Serialises an Email into a raw RFC 5322 / MIME message string. Produces a
 * text/plain message, or a multipart/alternative message whenever HTML is
 * present (a plain-text part is generated from the HTML when none is supplied).
 */
class MessageRenderer
{
    private const CRLF = "\r\n";

    private const CHARSET = 'UTF-8';

    /**
     * Human-readable X-Priority values keyed by the numeric priority level.
     */
    private const PRIORITIES = [
        1 => '1 (Highest)',
        2 => '2 (High)',
        3 => '3 (Normal)',
        4 => '4 (Low)',
        5 => '5 (Lowest)',
    ];

    /**
     * The header set produced by the last render(), exposed for the debugger.
     *
     * @var array<string, string>
     */
    private array $renderedHeaders = [];

    public function render(Email $email): string
    {
        [$contentHeaders, $body] = $this->buildBody($email);

        $this->renderedHeaders = array_merge(
            $this->buildHeaders($email),
            $contentHeaders,
        );

        $headerString = '';

        foreach ($this->renderedHeaders as $name => $value) {
            $headerString .= $name . ': ' . $value . self::CRLF;
        }

        return $headerString . self::CRLF . $body;
    }

    /**
     * Returns the header set produced by the most recent render().
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->renderedHeaders;
    }

    /**
     * Builds the envelope and structural headers, excluding the content headers
     * which depend on the body shape.
     *
     * @return array<string, string>
     */
    private function buildHeaders(Email $email): array
    {
        $headers = [];

        if ($email->from instanceof Address) {
            $headers['From'] = $email->from->toString();
        }

        if ($email->to !== []) {
            $headers['To'] = $this->addressList($email->to);
        }

        if ($email->cc !== []) {
            $headers['Cc'] = $this->addressList($email->cc);
        }

        if ($email->replyTo instanceof Address) {
            $headers['Reply-To'] = $email->replyTo->toString();
        }

        if ($email->returnPath !== null) {
            $headers['Return-Path'] = '<' . $email->returnPath . '>';
            $headers['Sender']      = $email->returnPath;
        }

        $headers['Subject']      = $email->subject;
        $headers['Date']         = date('r');
        $headers['Message-ID']   = $this->messageId($email);
        $headers['MIME-Version'] = '1.0';

        // Only surface a priority when it deviates from the Normal default.
        if ($email->priority !== 3 && isset(self::PRIORITIES[$email->priority])) {
            $headers['X-Priority'] = self::PRIORITIES[$email->priority];
        }

        // Arbitrary caller-supplied headers come last so they cannot clobber
        // the structural headers above.
        foreach ($email->headers as $name => $value) {
            $headers[$name] = $value;
        }

        return $headers;
    }

    /**
     * Builds the body and the content-related headers that describe it.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function buildBody(Email $email): array
    {
        $contentHeaders = [
            'Content-Type'              => 'text/plain; charset=' . self::CHARSET,
            'Content-Transfer-Encoding' => '8bit',
        ];

        return [$contentHeaders, (string) $email->textBody];
    }

    /**
     * Joins a list of addresses into a comma-separated header value.
     *
     * @param list<Address> $addresses
     */
    private function addressList(array $addresses): string
    {
        return implode(', ', array_map(static fn (Address $address): string => $address->toString(), $addresses));
    }

    /**
     * Derives a Message-ID from the sender's domain, falling back to localhost.
     */
    private function messageId(Email $email): string
    {
        $domain = 'localhost';

        if ($email->from instanceof Address && str_contains($email->from->email, '@')) {
            $domain = substr((string) strrchr($email->from->email, '@'), 1);
        }

        return '<' . bin2hex(random_bytes(16)) . '@' . $domain . '>';
    }
}
