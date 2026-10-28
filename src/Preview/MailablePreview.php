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

namespace Myth\Postal\Preview;

use Throwable;

/**
 * The rendered preview of one Mailable, as returned by MailablePreviewer::render().
 * When building or rendering the Mailable threw, $error holds the exception and
 * the message fields are null.
 */
final readonly class MailablePreview
{
    /**
     * @param class-string   $class       The previewed Mailable
     * @param Throwable|null $error       What building or rendering threw, if anything
     * @param string|null    $subject     The message subject
     * @param string|null    $htmlBody    The HTML body, or null when the message has none
     * @param string|null    $text        The plain-text body, derived from the HTML when not supplied
     * @param bool           $textAuto    Whether $text was derived from the HTML body
     * @param string|null    $rawMime     The full MIME message
     * @param list<string>   $attachments The attachment file names
     */
    public function __construct(
        public string $class,
        public ?Throwable $error = null,
        public ?string $subject = null,
        public ?string $htmlBody = null,
        public ?string $text = null,
        public bool $textAuto = false,
        public ?string $rawMime = null,
        public array $attachments = [],
    ) {
    }
}
