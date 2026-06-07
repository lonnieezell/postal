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
 * A mutable email message builder. Methods mutate the instance and return it
 * so calls can be chained.
 */
class Email
{
    public ?Address $from    = null;
    public ?Address $replyTo = null;

    /**
     * @var list<Address>
     */
    public array $to = [];

    /**
     * @var list<Address>
     */
    public array $cc = [];

    /**
     * @var list<Address>
     */
    public array $bcc = [];

    public string $subject   = '';
    public ?string $htmlBody = null;
    public ?string $textBody = null;

    /**
     * @var array<string, string>
     */
    public array $headers = [];

    /**
     * Provider metadata (tags) attached to the message, mapped by API transports
     * onto their provider's tagging feature (e.g. SES EmailTags). Ignored by
     * SMTP and other non-API transports.
     *
     * @var array<string, string>
     */
    public array $metadata = [];

    /**
     * File attachments and inline images, rendered at send time.
     *
     * @var list<Attachment>
     */
    public array $attachments = [];

    public int $priority       = 3;
    public ?string $returnPath = null;

    /**
     * When true (the default), the renderer scans the HTML body for embeddable
     * image sources — data: URIs and local file paths — turns each into an
     * inline attachment, and rewrites the reference to a "cid:" URL. Set to
     * false to leave the HTML untouched.
     */
    public bool $autoEmbedImages = true;

    /**
     * The Mailable class that composed this message, set by Mailable::send().
     * Lets the fake transport filter recorded messages by mailable type.
     */
    public ?string $mailableClass = null;

    /**
     * When true, the renderer hard-wraps the plain-text part at $wrapChars so
     * the wrap survives delivery (unlike quoted-printable soft wrapping).
     */
    public bool $wordWrap = false;

    public int $wrapChars = 76;

    public function from(string $address, string $name = ''): static
    {
        $this->from = new Address($address, $name);

        return $this;
    }

    public function replyTo(string $address, string $name = ''): static
    {
        $this->replyTo = new Address($address, $name);

        return $this;
    }

    /**
     * @param list<string>|string $address
     */
    public function to(array|string $address, string $name = ''): static
    {
        $this->addRecipients($this->to, $address, $name);

        return $this;
    }

    /**
     * @param list<string>|string $address
     */
    public function cc(array|string $address, string $name = ''): static
    {
        $this->addRecipients($this->cc, $address, $name);

        return $this;
    }

    /**
     * @param list<string>|string $address
     */
    public function bcc(array|string $address, string $name = ''): static
    {
        $this->addRecipients($this->bcc, $address, $name);

        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function html(string $html): static
    {
        $this->htmlBody = $html;

        return $this;
    }

    public function text(string $text): static
    {
        $this->textBody = $text;

        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * Attaches a provider metadata tag to the message. API transports map these
     * onto their tagging feature; unsupported keys/values are dropped and logged
     * by the transport rather than failing the send.
     */
    public function metadata(string $key, string $value): static
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    public function priority(int $level): static
    {
        $this->priority = $level;

        return $this;
    }

    public function returnPath(string $address): static
    {
        $this->returnPath = $address;

        return $this;
    }

    /**
     * Attaches a file by path. The file is read lazily at render time, so it
     * must exist when the message is sent rather than when this is called.
     */
    public function attach(string $path, string $name = '', string $mime = ''): static
    {
        $this->attachments[] = Attachment::fromPath($path, $name === '' ? null : $name, $mime === '' ? null : $mime);

        return $this;
    }

    /**
     * Attaches raw bytes carried in memory under the given filename.
     */
    public function attachData(string $data, string $name, string $mime = ''): static
    {
        $this->attachments[] = Attachment::fromData($data, $name, $mime === '' ? null : $mime);

        return $this;
    }

    /**
     * Embeds an image by path as an inline part referenceable from the HTML body
     * with a "cid:<cid>" URL. The file is read lazily at render time.
     */
    public function embedImage(string $path, string $cid, string $name = '', string $mime = ''): static
    {
        $this->attachments[] = Attachment::embed($path, $cid, $name === '' ? null : $name, $mime === '' ? null : $mime);

        return $this;
    }

    /**
     * Sets the List-Unsubscribe header explicitly. This value always takes
     * precedence over any auto-injected header from UnsubscribeUrlInterface.
     */
    public function listUnsubscribe(string $url): static
    {
        $this->headers['List-Unsubscribe'] = '<' . $url . '>';

        return $this;
    }

    /**
     * Appends one or more recipients to the given list. A string with a name
     * uses the name; an array is treated as a list of "Name <email>" or bare
     * address strings.
     *
     * @param list<Address>       $bucket
     * @param list<string>|string $address
     */
    private function addRecipients(array &$bucket, array|string $address, string $name): void
    {
        if (is_array($address)) {
            foreach ($address as $entry) {
                $bucket[] = Address::fromString($entry);
            }

            return;
        }

        $bucket[] = $name === '' ? Address::fromString($address) : new Address($address, $name);
    }
}
