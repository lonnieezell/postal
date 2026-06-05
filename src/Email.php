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

    public int $priority       = 3;
    public ?string $returnPath = null;

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
