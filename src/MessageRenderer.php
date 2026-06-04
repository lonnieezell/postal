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
    private const CRLF    = "\r\n";
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

    /**
     * Serialises the email into a complete raw MIME message (headers, a blank
     * line, then the body) and records the header set for headers().
     */
    public function render(Email $email): string
    {
        [$contentHeaders, $body] = $this->buildBody($email);

        $headers = array_merge($this->buildHeaders($email), $contentHeaders);

        $this->renderedHeaders = [];
        $headerString          = '';

        foreach ($headers as $name => $value) {
            // Strip CR/LF from both name and value to prevent header injection.
            $name  = $this->stripNewlines($name);
            $value = $this->stripNewlines($value);

            $this->renderedHeaders[$name] = $value;
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
        // Seed with caller-supplied headers first so the structural headers
        // assigned below always take precedence over a colliding custom header.
        $headers = $email->headers;

        if ($email->from instanceof Address) {
            $headers['From'] = $this->renderAddress($email->from);
        }

        if ($email->to !== []) {
            $headers['To'] = $this->addressList($email->to);
        }

        if ($email->cc !== []) {
            $headers['Cc'] = $this->addressList($email->cc);
        }

        if ($email->replyTo instanceof Address) {
            $headers['Reply-To'] = $this->renderAddress($email->replyTo);
        }

        if ($email->returnPath !== null) {
            $headers['Return-Path'] = '<' . $email->returnPath . '>';
            $headers['Sender']      = $email->returnPath;
        }

        $headers['Subject']      = $this->encodeHeader($email->subject);
        $headers['Date']         = date('r');
        $headers['Message-ID']   = $this->messageId($email);
        $headers['MIME-Version'] = '1.0';

        // Only surface a priority when it deviates from the Normal default.
        if ($email->priority !== 3 && isset(self::PRIORITIES[$email->priority])) {
            $headers['X-Priority'] = self::PRIORITIES[$email->priority];
        }

        return $headers;
    }

    /**
     * Builds the body and the content-related headers that describe it. HTML
     * always yields multipart/alternative; a plain-text fallback is generated
     * from the HTML when none was supplied.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function buildBody(Email $email): array
    {
        if ($email->htmlBody === null) {
            $contentHeaders = [
                'Content-Type'              => 'text/plain; charset=' . self::CHARSET,
                'Content-Transfer-Encoding' => '8bit',
            ];

            return [$contentHeaders, $this->wrapText((string) $email->textBody)];
        }

        $text     = $this->wrapText($email->textBody ?? $this->htmlToText($email->htmlBody));
        $html     = $this->toCrlf($email->htmlBody);
        $boundary = uniqid('B_ALT_', true);

        $contentHeaders = [
            'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = '--' . $boundary . self::CRLF
            . 'Content-Type: text/plain; charset=' . self::CHARSET . self::CRLF
            . 'Content-Transfer-Encoding: 8bit' . self::CRLF . self::CRLF
            . $text . self::CRLF . self::CRLF
            . '--' . $boundary . self::CRLF
            . 'Content-Type: text/html; charset=' . self::CHARSET . self::CRLF
            . 'Content-Transfer-Encoding: 8bit' . self::CRLF . self::CRLF
            . $html . self::CRLF . self::CRLF
            . '--' . $boundary . '--' . self::CRLF;

        return [$contentHeaders, $body];
    }

    /**
     * Produces a basic plain-text rendering of an HTML body for the text part:
     * anchors become "text (url)", block-level tags become line breaks, the
     * remaining tags are stripped and HTML entities decoded.
     */
    private function htmlToText(string $html): string
    {
        // strip_tags() keeps the *contents* of removed tags, so script/style/head
        // blocks would dump their CSS/JS/metadata into the text. Remove them whole
        // before any other conversion.
        $html = preg_replace(
            '#<(script|style|head)\b[^>]*>.*?</\1>#is',
            '',
            $html,
        ) ?? $html;

        // Anchors collapse to their label followed by the URL in parentheses.
        $text = preg_replace_callback(
            '/<a\b[^>]*\bhref=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is',
            static fn (array $matches): string => trim(strip_tags($matches[3])) . ' (' . $matches[2] . ')',
            $html,
        ) ?? $html;

        // Block-level tags (and <br>) mark line breaks in the plain-text view.
        $text = preg_replace(
            '/<\/?(?:p|div|br|h[1-6]|li|ul|ol|tr|table|blockquote|section|article|header|footer)\b[^>]*>/i',
            "\n",
            $text,
        ) ?? $text;

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, self::CHARSET);

        // Tidy the whitespace left behind by the substitutions above.
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Joins a list of addresses into a comma-separated header value.
     *
     * @param list<Address> $addresses
     */
    private function addressList(array $addresses): string
    {
        return implode(', ', array_map($this->renderAddress(...), $addresses));
    }

    /**
     * Renders an address while leaving the addr-spec literal. A pure-ASCII
     * display name is wrapped in an escaped quoted-string so specials such as a
     * comma cannot split the address list; a non-ASCII name is RFC 2047-encoded.
     */
    private function renderAddress(Address $address): string
    {
        if ($address->name === '') {
            return $address->email;
        }

        if (preg_match('/[^\x20-\x7E]/', $address->name) === 1) {
            $name = $this->encodeHeader($address->name);
        } else {
            $name = '"' . addcslashes($address->name, "\0..\37\177'\"\\") . '"';
        }

        return $name . ' <' . $address->email . '>';
    }

    /**
     * RFC 2047 "Q" encodes a header value when it contains non-ASCII bytes;
     * pure printable-ASCII values are returned unchanged.
     */
    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value) !== 1) {
            return $value;
        }

        $encoded = '';

        foreach (str_split($value) as $char) {
            if ($char === ' ') {
                $encoded .= '_';
            } elseif (preg_match('/[A-Za-z0-9]/', $char) === 1) {
                $encoded .= $char;
            } else {
                $encoded .= '=' . strtoupper(bin2hex($char));
            }
        }

        return '=?' . self::CHARSET . '?Q?' . $encoded . '?=';
    }

    /**
     * Wraps text at 76 characters on word boundaries and normalises every line
     * ending to CRLF as required by RFC 5322.
     */
    private function wrapText(string $text): string
    {
        $normalised = (string) preg_replace('/\r\n|\r|\n/', "\n", $text);

        $wrapped = implode("\n", array_map(
            static fn (string $line): string => wordwrap($line, 76, "\n", false),
            explode("\n", $normalised),
        ));

        return $this->toCrlf($wrapped);
    }

    /**
     * Normalises all line endings in a string to CRLF.
     */
    private function toCrlf(string $text): string
    {
        return (string) preg_replace('/\r\n|\r|\n/', self::CRLF, $text);
    }

    /**
     * Removes CR and LF so a value cannot inject additional header lines.
     */
    private function stripNewlines(string $value): string
    {
        return str_replace(["\r", "\n"], '', $value);
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
