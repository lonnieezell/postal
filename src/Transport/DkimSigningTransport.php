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

use Myth\Postal\Email;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\MessageRenderer;
use Myth\Postal\SendResult;
use OpenSSLAsymmetricKey;

/**
 * A decorator that DKIM-signs a message: it renders the MIME once, computes a
 * relaxed/relaxed rsa-sha256 DKIM-Signature, prepends it, and hands the signed
 * bytes to its inner raw-MIME transport via Email::$rawMessage — so the bytes
 * that were signed are the exact bytes delivered.
 */
final readonly class DkimSigningTransport implements TransportInterface
{
    private const CRLF = "\r\n";

    /**
     * The header fields to sign when present, in signing order. From is always
     * signed (RFC 6376 requires it).
     */
    private const SIGNED_HEADERS = [
        'From',
        'Reply-To',
        'To',
        'Cc',
        'Subject',
        'Date',
        'Message-ID',
        'MIME-Version',
        'Content-Type',
    ];

    private string $domain;
    private string $selector;
    private OpenSSLAsymmetricKey $privateKey;

    /**
     * @param array<string, mixed> $dkim the leaf's "dkim" config: domain, selector, privateKey
     */
    public function __construct(private RawMimeTransport $inner, array $dkim)
    {
        $domain   = (string) ($dkim['domain'] ?? '');
        $selector = (string) ($dkim['selector'] ?? '');

        if ($domain === '') {
            throw PostalException::forInvalidDkimConfig('a "domain"');
        }

        if ($selector === '') {
            throw PostalException::forInvalidDkimConfig('a "selector"');
        }

        $this->domain     = $domain;
        $this->selector   = $selector;
        $this->privateKey = $this->loadPrivateKey($dkim['privateKey'] ?? null);
    }

    public function send(Email $email): SendResult
    {
        $message = (new MessageRenderer())->render($email);

        $clone             = clone $email;
        $clone->rawMessage = $this->sign($message);

        return $this->inner->send($clone);
    }

    public function ping(): bool
    {
        return $this->inner->ping();
    }

    /**
     * Signs the rendered message and returns it with the DKIM-Signature header
     * prepended.
     */
    private function sign(string $message): string
    {
        [$headerBlock, $body] = array_pad(explode(self::CRLF . self::CRLF, $message, 2), 2, '');

        $headers = $this->parseHeaders($headerBlock);
        $signed  = $this->selectSignedHeaders($headers);

        $bodyHash = base64_encode(hash('sha256', $this->canonicalizeBody($body), true));

        // The signature is computed over the signed headers plus the
        // DKIM-Signature header itself with an empty b= tag.
        $template = $this->signatureValue($signed, $bodyHash, '');
        $input    = $this->signingInput($headers, $signed, $template);

        openssl_sign($input, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        $value = $this->signatureValue($signed, $bodyHash, base64_encode($signature));

        return 'DKIM-Signature: ' . $value . self::CRLF . $message;
    }

    /**
     * Parses an unfolded header block (one header per line) into an ordered
     * name => value map. The renderer never folds, so no continuation handling
     * is needed.
     *
     * @return array<string, string>
     */
    private function parseHeaders(string $headerBlock): array
    {
        $headers = [];

        foreach (explode(self::CRLF, $headerBlock) as $line) {
            $colon = strpos($line, ':');

            if ($colon === false) {
                continue;
            }

            $headers[substr($line, 0, $colon)] = ltrim(substr($line, $colon + 1));
        }

        return $headers;
    }

    /**
     * The header names to sign: the candidates that are actually present, in
     * signing order.
     *
     * @param array<string, string> $headers
     *
     * @return list<string>
     */
    private function selectSignedHeaders(array $headers): array
    {
        $present = [];

        foreach (self::SIGNED_HEADERS as $candidate) {
            foreach (array_keys($headers) as $name) {
                if (strcasecmp($name, $candidate) === 0) {
                    $present[] = $name;
                    break;
                }
            }
        }

        return $present;
    }

    /**
     * Builds the DKIM-Signature tag value for the given body hash and signature
     * (pass an empty signature to build the template that is signed).
     *
     * @param list<string> $signed
     */
    private function signatureValue(array $signed, string $bodyHash, string $signature): string
    {
        return sprintf(
            'v=1; a=rsa-sha256; c=relaxed/relaxed; d=%s; s=%s; t=%d; h=%s; bh=%s; b=%s',
            $this->domain,
            $this->selector,
            time(),
            implode(':', $signed),
            $bodyHash,
            $signature,
        );
    }

    /**
     * Assembles the relaxed signing input: each signed header canonicalized and
     * CRLF-terminated, then the DKIM-Signature header (empty b=) with no
     * trailing CRLF.
     *
     * @param array<string, string> $headers
     * @param list<string>          $signed
     */
    private function signingInput(array $headers, array $signed, string $template): string
    {
        $input = '';

        foreach ($signed as $name) {
            $input .= $this->canonicalizeHeader($name, $headers[$name]) . self::CRLF;
        }

        return $input . 'dkim-signature:' . $this->canonicalizeValue($template);
    }

    /**
     * Relaxed header canonicalization (RFC 6376 §3.4.2): lowercase name, no WSP
     * around the colon, collapsed and trimmed value.
     */
    private function canonicalizeHeader(string $name, string $value): string
    {
        return strtolower($name) . ':' . $this->canonicalizeValue($value);
    }

    /**
     * Collapses WSP runs to a single space and trims, as relaxed canonicalization
     * requires of a header value.
     */
    private function canonicalizeValue(string $value): string
    {
        return trim((string) preg_replace('/[ \t]+/', ' ', $value));
    }

    /**
     * Relaxed body canonicalization (RFC 6376 §3.4.4): collapse WSP runs, strip
     * trailing WSP per line, drop trailing empty lines, and end a non-empty body
     * with a single CRLF.
     */
    private function canonicalizeBody(string $body): string
    {
        $body  = (string) preg_replace('/\r\n|\r|\n/', self::CRLF, $body);
        $lines = explode(self::CRLF, $body);

        foreach ($lines as $i => $line) {
            $lines[$i] = rtrim((string) preg_replace('/[ \t]+/', ' ', $line), ' ');
        }

        $body = (string) preg_replace('/(\r\n)+$/', '', implode(self::CRLF, $lines));

        return $body === '' ? '' : $body . self::CRLF;
    }

    /**
     * Resolves the configured private key (a PEM string or a readable file path)
     * into an OpenSSL key, throwing a clear config error on anything invalid.
     */
    private function loadPrivateKey(mixed $key): OpenSSLAsymmetricKey
    {
        if (! is_string($key) || $key === '') {
            throw PostalException::forInvalidDkimConfig('a "privateKey" (PEM string or file path)');
        }

        if (! str_contains($key, 'BEGIN')) {
            if (! is_file($key) || ! is_readable($key)) {
                throw PostalException::forInvalidDkimConfig('a readable "privateKey" file');
            }

            $key = (string) file_get_contents($key);
        }

        $resource = openssl_pkey_get_private($key);

        if ($resource === false) {
            throw PostalException::forInvalidDkimConfig('a valid "privateKey"');
        }

        return $resource;
    }
}
