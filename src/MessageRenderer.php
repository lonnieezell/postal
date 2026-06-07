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
        $email = $this->embedInlineImages($email);

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
     * Scans the HTML body for embeddable image sources, turns each into an inline
     * attachment and rewrites the reference to a "cid:" URL. data: URIs are
     * decoded in memory and local file paths are read at render time; remote
     * (http/https) and existing cid: sources are left untouched. Returns a clone
     * carrying the rewritten body and the extra attachments, or the original
     * email when the feature is off or nothing was embedded.
     *
     * render() applies this itself; it is public so a transport can apply it up
     * front to decide how to carry the message. The pass is idempotent — a body
     * whose images are already cid: references yields no further attachments.
     */
    public function embedInlineImages(Email $email): Email
    {
        if (! $email->autoEmbedImages || $email->htmlBody === null) {
            return $email;
        }

        /** @var list<Attachment> $embedded */
        $embedded = [];

        /** @var array<string, string> $bySource */
        $bySource = [];

        $html = preg_replace_callback(
            '/<img\b[^>]*>/i',
            function (array $match) use (&$embedded, &$bySource): string {
                $tag = $match[0];

                if (preg_match('/\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s">]+))/i', $tag, $src) !== 1) {
                    return $tag;
                }

                $value = $src[1] !== '' ? $src[1] : (($src[2] ?? '') !== '' ? $src[2] : ($src[3] ?? ''));
                $cid   = $this->embedSource(trim($value), $bySource, $embedded);

                if ($cid === null) {
                    return $tag;
                }

                return str_replace($src[0], 'src="cid:' . $cid . '"', $tag);
            },
            $email->htmlBody,
        ) ?? $email->htmlBody;

        if ($embedded === []) {
            return $email;
        }

        $clone              = clone $email;
        $clone->htmlBody    = $html;
        $clone->attachments = [...$email->attachments, ...$embedded];

        return $clone;
    }

    /**
     * Resolves one image source to a Content-ID, appending the inline attachment
     * it created (deduplicating repeats). Returns null for sources that must be
     * left as-is: empty, remote, an existing cid:, or a non-image/unreadable
     * local file.
     *
     * @param array<string, string> $bySource
     * @param list<Attachment>      $embedded
     */
    private function embedSource(string $source, array &$bySource, array &$embedded): ?string
    {
        if ($source === '' || preg_match('#^(cid:|https?:|//)#i', $source) === 1) {
            return null;
        }

        if (isset($bySource[$source])) {
            return $bySource[$source];
        }

        $attachment = str_starts_with(strtolower($source), 'data:')
            ? $this->dataUriImage($source)
            : $this->localImage($source);

        if ($attachment === null) {
            return null;
        }

        $bySource[$source] = (string) $attachment->cid;
        $embedded[]        = $attachment;

        return (string) $attachment->cid;
    }

    /**
     * Builds an inline attachment from a base64 data: URI that declares an image
     * MIME type, or null for anything else.
     */
    private function dataUriImage(string $source): ?Attachment
    {
        if (preg_match('#^data:([^;,]*)(;base64)?,(.*)$#is', $source, $m) !== 1) {
            return null;
        }

        $mime = strtolower(trim($m[1]));

        if (! str_starts_with($mime, 'image/') || $m[2] === '') {
            return null;
        }

        $bytes = base64_decode($m[3], true);

        if ($bytes === false) {
            return null;
        }

        $cid = substr(hash('sha256', $bytes), 0, 24) . '@postal';

        return Attachment::embedData($bytes, $cid, 'image-' . substr($cid, 0, 8) . $this->imageExtension($mime), $mime);
    }

    /**
     * Builds an inline attachment from a readable local image file, or null when
     * the path is not a readable file or is not an image.
     */
    private function localImage(string $source): ?Attachment
    {
        if (! is_file($source) || ! is_readable($source)) {
            return null;
        }

        $cid        = substr(hash('sha256', (string) realpath($source)), 0, 24) . '@postal';
        $attachment = Attachment::embed($source, $cid);

        return str_starts_with($attachment->mimeType(), 'image/') ? $attachment : null;
    }

    /**
     * Maps a common image MIME type to a filename extension.
     */
    private function imageExtension(string $mime): string
    {
        return match ($mime) {
            'image/png'                                => '.png',
            'image/jpeg'                               => '.jpg',
            'image/gif'                                => '.gif',
            'image/webp'                               => '.webp',
            'image/svg+xml'                            => '.svg',
            'image/bmp'                                => '.bmp',
            'image/x-icon', 'image/vnd.microsoft.icon' => '.ico',
            default                                    => '',
        };
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
        // Content-* headers are dropped: the renderer owns the content headers,
        // and a stray custom one (e.g. Content-Transfer-Encoding) must not leak
        // onto a multipart container, which carries none of its own.
        $headers = array_filter(
            $email->headers,
            static fn (string $name): bool => ! str_starts_with(strtolower($name), 'content-'),
            ARRAY_FILTER_USE_KEY,
        );

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
     * Builds the message body and its content headers, wrapping the textual
     * content in multipart/related when inline images are present and in
     * multipart/mixed when file attachments are present. The resulting nesting is
     * mixed { related { alternative } } so each container only appears when it has
     * something to hold.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function buildBody(Email $email): array
    {
        [$headers, $body] = $this->buildContentPart($email);

        $inline      = array_values(array_filter($email->attachments, static fn (Attachment $a): bool => $a->disposition === 'inline'));
        $attachments = array_values(array_filter($email->attachments, static fn (Attachment $a): bool => $a->disposition !== 'inline'));

        if ($inline !== []) {
            [$headers, $body] = $this->wrapMultipart('related', $headers, $body, $inline);
        }

        if ($attachments !== []) {
            [$headers, $body] = $this->wrapMultipart('mixed', $headers, $body, $attachments);
        }

        return [$headers, $body];
    }

    /**
     * Wraps an already-rendered root part (its headers and body) in a multipart
     * container of the given subtype, appending one binary part per attachment.
     * A multipart/related container also names the root part's media type with a
     * type parameter, as RFC 2387 requires; multipart/mixed carries none.
     *
     * @param array<string, string> $rootHeaders
     * @param list<Attachment>      $attachments
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function wrapMultipart(string $subtype, array $rootHeaders, string $rootBody, array $attachments): array
    {
        $boundary = uniqid('B_' . strtoupper($subtype) . '_', true);

        $parts = [$this->part($rootHeaders, $rootBody)];

        foreach ($attachments as $attachment) {
            [$partHeaders, $partBody] = $this->binaryPart($attachment);
            $parts[]                  = $this->part($partHeaders, $partBody);
        }

        $contentType = 'multipart/' . $subtype;

        if ($subtype === 'related') {
            $contentType .= '; type="' . $this->mediaType($rootHeaders['Content-Type'] ?? 'text/plain') . '"';
        }

        $headers = [
            'Content-Type' => $contentType . '; boundary="' . $boundary . '"',
        ];

        return [$headers, $this->multipart($boundary, $parts)];
    }

    /**
     * Renders one MIME part: its headers, a blank line, then its body. Header
     * values are stripped of CR/LF so a part header (e.g. a filename) cannot
     * inject sibling headers.
     *
     * @param array<string, string> $headers
     */
    private function part(array $headers, string $body): string
    {
        $rendered = '';

        foreach ($headers as $name => $value) {
            $rendered .= $this->stripNewlines($name) . ': ' . $this->stripNewlines($value) . self::CRLF;
        }

        return $rendered . self::CRLF . $body;
    }

    /**
     * Assembles MIME parts into a multipart body delimited by the boundary, with
     * the closing delimiter appended.
     *
     * @param list<string> $parts
     */
    private function multipart(string $boundary, array $parts): string
    {
        $body = '';

        foreach ($parts as $part) {
            $body .= '--' . $boundary . self::CRLF . $part . self::CRLF;
        }

        return $body . '--' . $boundary . '--' . self::CRLF;
    }

    /**
     * Builds the headers and base64-encoded, line-chunked body for a binary
     * attachment or inline image.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function binaryPart(Attachment $attachment): array
    {
        $name = $this->quoteParam($attachment->name);

        $headers = [
            'Content-Type'              => $attachment->mimeType() . '; name="' . $name . '"',
            'Content-Transfer-Encoding' => 'base64',
            'Content-Disposition'       => $attachment->disposition . '; filename="' . $name . '"',
        ];

        if ($attachment->disposition === 'inline' && $attachment->cid !== null) {
            $headers['Content-ID'] = '<' . $attachment->cid . '>';
        }

        // Base64 with CRLF every 76 columns (RFC 2045 §6.8); drop the trailing
        // break chunk_split() adds so the multipart separator is not doubled.
        $body = rtrim(chunk_split(base64_encode($attachment->content()), 76, self::CRLF), "\r\n");

        return [$headers, $body];
    }

    /**
     * Returns the bare media type (e.g. "multipart/alternative") from a full
     * Content-Type value, dropping any parameters.
     */
    private function mediaType(string $contentType): string
    {
        return trim(explode(';', $contentType)[0]);
    }

    /**
     * Sanitises a value for use inside a quoted-string MIME parameter: CR/LF are
     * removed so the parameter cannot inject sibling headers, and embedded quotes
     * and backslashes are escaped so they cannot terminate the quoted-string.
     */
    private function quoteParam(string $value): string
    {
        return addcslashes($this->stripNewlines($value), '"\\');
    }

    /**
     * Builds the textual body and the content headers that describe it. HTML
     * always yields multipart/alternative; a plain-text fallback is generated
     * from the HTML when none was supplied.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function buildContentPart(Email $email): array
    {
        // Both parts are quoted-printable so long lines stay within the 998-octet
        // SMTP limit and the message is 7-bit clean without an 8BITMIME server.
        if ($email->htmlBody === null) {
            $contentHeaders = [
                'Content-Type'              => 'text/plain; charset=' . self::CHARSET,
                'Content-Transfer-Encoding' => 'quoted-printable',
            ];

            return [$contentHeaders, $this->quotedPrintable($this->wrap((string) $email->textBody, $email))];
        }

        $text     = $this->quotedPrintable($this->wrap($email->textBody ?? $this->htmlToText($email->htmlBody), $email));
        $html     = $this->quotedPrintable($email->htmlBody);
        $boundary = uniqid('B_ALT_', true);

        $contentHeaders = [
            'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body = '--' . $boundary . self::CRLF
            . 'Content-Type: text/plain; charset=' . self::CHARSET . self::CRLF
            . 'Content-Transfer-Encoding: quoted-printable' . self::CRLF . self::CRLF
            . $text . self::CRLF . self::CRLF
            . '--' . $boundary . self::CRLF
            . 'Content-Type: text/html; charset=' . self::CHARSET . self::CRLF
            . 'Content-Transfer-Encoding: quoted-printable' . self::CRLF . self::CRLF
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
            '/<(script|style|head)\b[^>]*>.*?<\/\1>/is',
            '',
            $html,
        ) ?? $html;

        // Anchors collapse to their label followed by the URL in parentheses.
        // The href may be double-quoted, single-quoted, or bare.
        $text = preg_replace_callback(
            '/<a\b[^>]*\bhref=(?:(["\'])(.*?)\1|([^\s>]+))[^>]*>(.*?)<\/a>/is',
            static function (array $matches): string {
                $url = $matches[2] !== '' ? $matches[2] : $matches[3];

                return trim(strip_tags($matches[4])) . ' (' . $url . ')';
            },
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
     * Hard-wraps the plain-text content at the email's wrapChars when word wrap
     * is enabled, leaving long space-less tokens (e.g. URLs) intact. When word
     * wrap is off the text is returned unchanged.
     */
    private function wrap(string $text, Email $email): string
    {
        if (! $email->wordWrap) {
            return $text;
        }

        $chars = $email->wrapChars > 0 ? $email->wrapChars : 76;

        return wordwrap($text, $chars, "\n", false);
    }

    /**
     * Normalises line endings to CRLF and quoted-printable encodes the text, so
     * every emitted line stays within the 998-octet SMTP limit (QP soft-wraps at
     * 76) and the part is 7-bit clean regardless of the body's encoding.
     */
    private function quotedPrintable(string $text): string
    {
        return quoted_printable_encode($this->toCrlf($text));
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
