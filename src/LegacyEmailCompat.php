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
 * Maps CodeIgniter 4's legacy Email setter surface onto the new Email builder
 * and the adapter's own state. Kept separate from the Email value object so the
 * value object carries none of the legacy API.
 *
 * @internal used only by {@see LegacyEmailAdapter}
 */
trait LegacyEmailCompat
{
    /**
     * @param string $from       a bare address or "Name <address>"
     * @param string $name       display name
     * @param string $returnPath envelope sender; defaults to $from when omitted
     */
    public function setFrom(string $from, string $name = '', ?string $returnPath = null): static
    {
        $from = $this->extractAddress($from);

        if (! $this->isValidEmail($from)) {
            $this->fail('Invalid From address: ' . $from);
            $this->validationFailed = true;

            return $this;
        }

        $this->email->from = new Address($from, $name);

        if ($returnPath !== null && $returnPath !== '') {
            $this->email->returnPath = $this->extractAddress($returnPath);
        }

        return $this;
    }

    public function setReplyTo(string $replyto, string $name = ''): static
    {
        $replyto = $this->extractAddress($replyto);

        if (! $this->isValidEmail($replyto)) {
            $this->fail('Invalid Reply-To address: ' . $replyto);
            $this->validationFailed = true;

            return $this;
        }

        $this->email->replyTo = new Address($replyto, $name);

        return $this;
    }

    /**
     * @param list<string>|string $to
     */
    public function setTo(array|string $to): static
    {
        $this->addAddresses('to', $to);

        return $this;
    }

    /**
     * @param list<string>|string $cc
     */
    public function setCC(array|string $cc): static
    {
        $this->addAddresses('cc', $cc);

        return $this;
    }

    /**
     * @param list<string>|string $bcc
     * @param int|string          $limit batch size; a numeric value turns on BCC batch mode
     */
    public function setBCC(array|string $bcc, int|string $limit = ''): static
    {
        if ($limit !== '' && is_numeric($limit)) {
            $this->BCCBatchMode = true;
            $this->BCCBatchSize = (int) $limit;
        }

        $this->addAddresses('bcc', $bcc);

        return $this;
    }

    public function setSubject(string $subject): static
    {
        $this->email->subject = $subject;

        return $this;
    }

    /**
     * Stores the body. Whether it becomes the HTML or text part is decided by
     * the mail type at send/render time, so call order does not matter.
     */
    public function setMessage(string $body): static
    {
        $this->body = rtrim(str_replace("\r", '', $body));

        return $this;
    }

    public function setAltMessage(string $str): static
    {
        $this->altMessage = $str;

        return $this;
    }

    public function setMailType(string $type = 'text'): static
    {
        $this->mailType = $type === 'html' ? 'html' : 'text';

        return $this;
    }

    public function setHeader(string $header, string $value): static
    {
        $this->email->header($header, str_replace(["\r", "\n"], '', $value));

        return $this;
    }

    public function setPriority(int $n = 3): static
    {
        $this->email->priority = ($n >= 1 && $n <= 5) ? $n : 3;

        return $this;
    }

    public function setWordWrap(bool $wordWrap = true): static
    {
        $this->wordWrap = $wordWrap;

        return $this;
    }

    public function setProtocol(string $protocol = 'mail'): static
    {
        $this->protocol = in_array($protocol, ['mail', 'sendmail', 'smtp'], true)
            ? strtolower($protocol)
            : 'mail';

        return $this;
    }

    /**
     * Accepted for source compatibility. Line endings are owned by the renderer,
     * so this is a safe no-op.
     */
    public function setNewline(string $newline = "\n"): static
    {
        return $this;
    }

    /**
     * Accepted for source compatibility. Line endings are owned by the renderer,
     * so this is a safe no-op.
     */
    public function setCRLF(string $CRLF = "\n"): static
    {
        return $this;
    }

    /**
     * Records an attachment. Attachment rendering is not implemented yet (issue
     * #17); a send with attachments fails with a debugger note rather than
     * silently dropping them.
     *
     * @param string      $file
     * @param string      $disposition
     * @param string|null $newname
     * @param string      $mime
     */
    public function attach($file, $disposition = '', $newname = null, $mime = ''): static
    {
        $this->attachments[] = [
            'name'        => [$file, $newname],
            'disposition' => $disposition === '' ? 'attachment' : $disposition,
            'mime'        => $mime,
            'cid'         => null,
        ];

        return $this;
    }

    /**
     * Generates and stores a Content-ID for a previously attached file, matched
     * by its path or buffer name. Returns the CID, or false when no match.
     *
     * @return false|string
     */
    public function setAttachmentCID(string $filename)
    {
        foreach ($this->attachments as $i => $attachment) {
            if ($attachment['name'][0] === $filename || $attachment['name'][1] === $filename) {
                $base                         = basename((string) ($attachment['name'][1] ?? $attachment['name'][0]));
                $cid                          = uniqid($base . '@', true);
                $this->attachments[$i]['cid'] = $cid;

                return $cid;
            }
        }

        return false;
    }

    /**
     * @param array<int, string>|string $email
     */
    public function validateEmail(array|string $email): bool
    {
        if (! is_array($email)) {
            $this->fail('validateEmail() expects an array of addresses.');

            return false;
        }

        foreach ($email as $address) {
            if (! $this->isValidEmail($address)) {
                $this->fail('Invalid email address: ' . $address);

                return false;
            }
        }

        return true;
    }

    public function isValidEmail(string $email): bool
    {
        if (
            function_exists('idn_to_ascii')
            && defined('INTL_IDNA_VARIANT_UTS46')
            && ($atpos = strpos($email, '@')) !== false
        ) {
            $ascii = idn_to_ascii(substr($email, $atpos + 1), 0, INTL_IDNA_VARIANT_UTS46);

            if ($ascii !== false) {
                $email = substr($email, 0, $atpos + 1) . $ascii;
            }
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Strips a display name from an address (or each address in an array),
     * leaving only the addr-spec.
     *
     * @param array<int, string>|string $email
     *
     * @return ($email is array ? list<string> : string)
     */
    public function cleanEmail(array|string $email): array|string
    {
        if (! is_array($email)) {
            return $this->extractAddress($email);
        }

        return array_map($this->extractAddress(...), $email);
    }

    /**
     * Adds one or more recipients to the named bucket. Invalid addresses are
     * swallowed: each is recorded in the debugger and skipped so a send fails
     * cleanly instead of throwing.
     *
     * @param 'bcc'|'cc'|'to'           $bucket
     * @param array<int, string>|string $addresses
     */
    private function addAddresses(string $bucket, array|string $addresses): void
    {
        foreach ($this->stringToArray($addresses) as $entry) {
            $address = Address::fromString($entry);

            if (! $this->isValidEmail($address->email)) {
                $this->fail('Invalid email address: ' . $entry);
                $this->validationFailed = true;

                continue;
            }

            if ($bucket === 'to') {
                $this->email->to[] = $address;
            } elseif ($bucket === 'cc') {
                $this->email->cc[] = $address;
            } else {
                $this->email->bcc[] = $address;
            }
        }
    }

    /**
     * Splits a comma/whitespace-separated address string into a list, mirroring
     * CodeIgniter's behaviour. Arrays pass through unchanged.
     *
     * @param array<int, string>|string $email
     *
     * @return array<int, string>
     */
    private function stringToArray(array|string $email): array
    {
        if (is_array($email)) {
            return $email;
        }

        if (str_contains($email, ',')) {
            $parts = preg_split('/[\s,]/', $email, -1, PREG_SPLIT_NO_EMPTY);

            return $parts === false ? [] : $parts;
        }

        return [trim($email)];
    }

    /**
     * Returns the addr-spec from a bare address or a "Name <address>" string.
     */
    private function extractAddress(string $address): string
    {
        if (preg_match('/<(.*)>/', $address, $match) === 1) {
            return trim($match[1]);
        }

        return trim($address);
    }
}
