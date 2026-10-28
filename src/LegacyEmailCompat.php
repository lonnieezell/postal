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
     * @param string      $from       a bare address or "Name <address>"
     * @param string      $name       display name
     * @param string|null $returnPath envelope sender; defaults to $from when omitted
     */
    public function setFrom($from, $name = '', $returnPath = null): static
    {
        $from = $this->extractAddress((string) $from);

        if (! $this->isValidEmail($from)) {
            $this->fail('Invalid From address: ' . $from);
            $this->validationFailed = true;

            return $this;
        }

        $this->email->from = new Address($from, (string) $name);

        // CI4 parity: the envelope sender defaults to the From address.
        $this->email->returnPath = ($returnPath !== null && $returnPath !== '')
            ? $this->extractAddress((string) $returnPath)
            : $from;

        return $this;
    }

    /**
     * @param string $replyto
     * @param string $name
     */
    public function setReplyTo($replyto, $name = ''): static
    {
        $replyto = $this->extractAddress((string) $replyto);

        if (! $this->isValidEmail($replyto)) {
            $this->fail('Invalid Reply-To address: ' . $replyto);
            $this->validationFailed = true;

            return $this;
        }

        $this->email->replyTo = new Address($replyto, (string) $name);

        return $this;
    }

    /**
     * @param list<string>|string $to
     */
    public function setTo($to): static
    {
        $this->addAddresses('to', $to);

        return $this;
    }

    /**
     * @param list<string>|string $cc
     */
    public function setCC($cc): static
    {
        $this->addAddresses('cc', $cc);

        return $this;
    }

    /**
     * @param list<string>|string $bcc
     * @param int|string          $limit batch size; a numeric value turns on BCC batch mode
     */
    public function setBCC($bcc, $limit = ''): static
    {
        if ($limit !== '' && is_numeric($limit)) {
            $this->BCCBatchMode = true;
            $this->BCCBatchSize = (int) $limit;
        }

        $this->addAddresses('bcc', $bcc);

        return $this;
    }

    /**
     * @param string $subject
     */
    public function setSubject($subject): static
    {
        $this->email->subject = (string) $subject;

        return $this;
    }

    /**
     * Stores the body. Whether it becomes the HTML or text part is decided by
     * the mail type at send/render time, so call order does not matter.
     *
     * @param string $body
     */
    public function setMessage($body): static
    {
        $this->body = rtrim(str_replace("\r", '', (string) $body));

        return $this;
    }

    /**
     * @param string $str
     */
    public function setAltMessage($str): static
    {
        $this->altMessage = (string) $str;

        return $this;
    }

    /**
     * @param string $type
     */
    public function setMailType($type = 'text'): static
    {
        $this->mailType = $type === 'html' ? 'html' : 'text';

        return $this;
    }

    /**
     * @param string $header
     * @param string $value
     */
    public function setHeader($header, $value): static
    {
        $this->email->header((string) $header, str_replace(["\r", "\n"], '', (string) $value));

        return $this;
    }

    /**
     * @param int $n
     */
    public function setPriority($n = 3): static
    {
        $n                     = (int) $n;
        $this->email->priority = ($n >= 1 && $n <= 5) ? $n : 3;

        return $this;
    }

    /**
     * @param bool $wordWrap
     */
    public function setWordWrap($wordWrap = true): static
    {
        $this->wordWrap = (bool) $wordWrap;

        return $this;
    }

    /**
     * @param string $protocol
     */
    public function setProtocol($protocol = 'mail'): static
    {
        $this->protocol = in_array($protocol, ['mail', 'sendmail', 'smtp'], true)
            ? strtolower($protocol)
            : 'mail';

        return $this;
    }

    /**
     * Accepted for source compatibility. Line endings are owned by the renderer,
     * so this is a safe no-op.
     *
     * @param string $newline
     */
    public function setNewline($newline = "\n"): static
    {
        return $this;
    }

    /**
     * Accepted for source compatibility. Line endings are owned by the renderer,
     * so this is a safe no-op.
     *
     * @param string $CRLF
     */
    public function setCRLF($CRLF = "\n"): static
    {
        return $this;
    }

    /**
     * Records a path-based attachment. It is translated to a native Attachment
     * and delivered when the message is sent; an unreadable path fails the send
     * with a debugger note rather than throwing.
     *
     * @param string      $file
     * @param string      $disposition
     * @param string|null $newname
     * @param string      $mime
     */
    public function attach($file, $disposition = '', $newname = null, $mime = ''): static
    {
        $this->attachmentRecords[] = [
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
     * @param string $filename
     *
     * @return false|string
     */
    public function setAttachmentCID($filename)
    {
        foreach ($this->attachmentRecords as $i => $attachment) {
            if ($attachment['name'][0] === $filename || $attachment['name'][1] === $filename) {
                $base                               = basename((string) ($attachment['name'][1] ?? $attachment['name'][0]));
                $cid                                = uniqid($base . '@', true);
                $this->attachmentRecords[$i]['cid'] = $cid;

                return $cid;
            }
        }

        return false;
    }

    /**
     * @param array<int, string>|string $email
     */
    public function validateEmail($email): bool
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

    /**
     * @param string $email
     */
    public function isValidEmail($email): bool
    {
        return Address::isValid((string) $email);
    }

    /**
     * Strips a display name from an address (or each address in an array),
     * leaving only the addr-spec.
     *
     * @param array<int, string>|string $email
     *
     * @return ($email is array ? list<string> : string)
     */
    public function cleanEmail($email): array|string
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
