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

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Attachment;
use Myth\Postal\Exceptions\PostalException;

/**
 * @internal
 */
final class AttachmentTest extends CIUnitTestCase
{
    public function testDataAttachmentReturnsItsBytes(): void
    {
        $attachment = Attachment::fromData('raw-bytes', 'note.txt', 'text/plain');

        $this->assertSame('raw-bytes', $attachment->content());
        $this->assertSame('note.txt', $attachment->name);
        $this->assertSame('text/plain', $attachment->mimeType());
        $this->assertSame('attachment', $attachment->disposition);
        $this->assertNull($attachment->cid);
    }

    public function testPathAttachmentIsReadLazily(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'postal');
        file_put_contents($path, 'file-contents');

        $attachment = Attachment::fromPath($path);

        // The bytes are only pulled when content() is called, not at construction.
        $this->assertSame('file-contents', $attachment->content());
        $this->assertSame(basename($path), $attachment->name);

        unlink($path);
    }

    public function testPathAttachmentDefaultsNameToBasename(): void
    {
        $attachment = Attachment::fromPath('/var/data/report.pdf');

        $this->assertSame('report.pdf', $attachment->name);
    }

    public function testExplicitNameOverridesBasename(): void
    {
        $attachment = Attachment::fromPath('/var/data/report.pdf', 'invoice.pdf');

        $this->assertSame('invoice.pdf', $attachment->name);
    }

    public function testUnreadablePathThrowsOnContent(): void
    {
        $attachment = Attachment::fromPath('/no/such/file.bin');

        $this->expectException(PostalException::class);
        $attachment->content();
    }

    public function testDataMimeDefaultsToOctetStream(): void
    {
        $attachment = Attachment::fromData('bytes', 'thing.unknown');

        $this->assertSame('application/octet-stream', $attachment->mimeType());
    }

    public function testInlineImageCarriesCidAndInlineDisposition(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'postal');
        file_put_contents($path, 'img-data');

        $attachment = Attachment::embed($path, 'logo123', 'logo.png', 'image/png');

        $this->assertSame('inline', $attachment->disposition);
        $this->assertSame('logo123', $attachment->cid);
        $this->assertSame('image/png', $attachment->mimeType());
        $this->assertSame('img-data', $attachment->content());

        unlink($path);
    }

    public function testEmbedDataCarriesInlineBytesCidAndMime(): void
    {
        $attachment = Attachment::embedData('img-bytes', 'logo123', 'logo.png', 'image/png');

        $this->assertSame('inline', $attachment->disposition);
        $this->assertSame('logo123', $attachment->cid);
        $this->assertSame('logo.png', $attachment->name);
        $this->assertSame('image/png', $attachment->mimeType());
        $this->assertSame('img-bytes', $attachment->content());
    }
}
