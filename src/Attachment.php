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

use Myth\Postal\Exceptions\PostalException;

/**
 * A file attached to an email, either as a path read lazily at render time or
 * as raw bytes carried in memory. Inline images additionally carry a Content-ID
 * so an HTML body can reference them with a "cid:" URL.
 */
final readonly class Attachment
{
    /**
     * @param string      $name        the filename shown to the recipient
     * @param string      $disposition 'attachment' or 'inline'
     * @param string|null $path        a filesystem path read lazily at render time
     * @param string|null $data        raw bytes carried in memory
     * @param string|null $mime        an explicit MIME type, or null to detect/default
     * @param string|null $cid         the Content-ID for an inline image
     */
    private function __construct(
        public string $name,
        public string $disposition,
        public ?string $path = null,
        public ?string $data = null,
        public ?string $mime = null,
        public ?string $cid = null,
    ) {
    }

    /**
     * A path-based attachment. The file is read lazily when content() is called,
     * not at construction. The display name defaults to the path's basename.
     */
    public static function fromPath(string $path, ?string $name = null, ?string $mime = null): self
    {
        return new self($name ?? basename($path), 'attachment', path: $path, mime: $mime);
    }

    /**
     * An attachment carrying raw bytes in memory.
     */
    public static function fromData(string $data, string $name, ?string $mime = null): self
    {
        return new self($name, 'attachment', data: $data, mime: $mime);
    }

    /**
     * A path-based inline image referenceable from HTML via the given Content-ID.
     * The file is read lazily when content() is called.
     */
    public static function embed(string $path, string $cid, ?string $name = null, ?string $mime = null): self
    {
        return new self($name ?? basename($path), 'inline', path: $path, mime: $mime, cid: $cid);
    }

    /**
     * Returns the attachment bytes, reading a path-based attachment from disk on
     * demand. An unreadable path raises a PostalException.
     */
    public function content(): string
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $bytes = @file_get_contents((string) $this->path);

        if ($bytes === false) {
            throw new PostalException('Could not read attachment file: ' . $this->path);
        }

        return $bytes;
    }

    /**
     * Resolves the MIME type: an explicit type wins; otherwise a path-based
     * attachment is sniffed from disk; everything else falls back to
     * application/octet-stream.
     */
    public function mimeType(): string
    {
        if ($this->mime !== null && $this->mime !== '') {
            return $this->mime;
        }

        if ($this->path !== null && is_file($this->path)) {
            $type = mime_content_type($this->path);

            if ($type !== false) {
                return $type;
            }
        }

        return 'application/octet-stream';
    }
}
