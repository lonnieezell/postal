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
use Myth\Postal\Config\Postal;
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Preview\MailablePreviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\Mailables\PreviewBroken;
use Tests\Support\Mailables\PreviewEmpty;
use Tests\Support\Mailables\PreviewHtmlOnly;
use Tests\Support\Mailables\PreviewMissingAttachment;
use Tests\Support\Mailables\PreviewWelcome;
use Tests\Support\Mailables\PreviewWithAttachment;
use Tests\Support\Mailables\WelcomeEmail;

/**
 * @internal
 */
final class MailablePreviewerTest extends CIUnitTestCase
{
    private function previewer(): MailablePreviewer
    {
        return new MailablePreviewer(['Tests\Support\Mailables' => SUPPORTPATH . 'Mailables']);
    }

    public function testDiscoverReturnsOnlyPreviewableMailables(): void
    {
        $found = $this->previewer()->discover();

        $this->assertContains(PreviewWelcome::class, $found);
        $this->assertContains(PreviewBroken::class, $found);
        $this->assertNotContains(WelcomeEmail::class, $found);
    }

    public function testDiscoverReturnsClassesSorted(): void
    {
        $found  = $this->previewer()->discover();
        $sorted = $found;
        sort($sorted);

        $this->assertSame($sorted, $found);
    }

    public function testRenderReturnsTheComposedMessage(): void
    {
        $preview = $this->previewer()->render(PreviewWithAttachment::class);

        $this->assertSame(PreviewWithAttachment::class, $preview->class);
        $this->assertNull($preview->error);
        $this->assertSame('Your invoice', $preview->subject);
        $this->assertSame('<p>Invoice attached</p>', $preview->htmlBody);
        $this->assertSame('Invoice attached', $preview->text);
        $this->assertFalse($preview->textAuto);
        $this->assertStringContainsString('Subject: Your invoice', (string) $preview->rawMime);
        $this->assertStringContainsString('multipart/mixed', (string) $preview->rawMime);
        $this->assertSame(['invoice.pdf'], $preview->attachments);
    }

    public function testRenderDerivesTextFromHtmlWhenNoneSupplied(): void
    {
        $preview = $this->previewer()->render(PreviewHtmlOnly::class);

        $this->assertTrue($preview->textAuto);
        $this->assertStringContainsString('the link (https://example.com)', (string) $preview->text);
    }

    public function testRenderOfAMessageWithNoBodyHasEmptyText(): void
    {
        $preview = $this->previewer()->render(PreviewEmpty::class);

        $this->assertNull($preview->htmlBody);
        $this->assertSame('', $preview->text);
        $this->assertFalse($preview->textAuto);
    }

    public function testRenderCapturesABuildErrorInsteadOfThrowing(): void
    {
        $preview = $this->previewer()->render(PreviewBroken::class);

        $this->assertSame(PreviewBroken::class, $preview->class);
        $this->assertInstanceOf(RuntimeException::class, $preview->error);
        $this->assertSame('preview blew up', $preview->error->getMessage());
        $this->assertNull($preview->subject);
        $this->assertNull($preview->rawMime);
    }

    public function testRenderCapturesAMimeRenderingErrorInsteadOfThrowing(): void
    {
        $preview = $this->previewer()->render(PreviewMissingAttachment::class);

        $this->assertInstanceOf(PostalException::class, $preview->error);
        $this->assertStringContainsString('/no/such/file.pdf', $preview->error->getMessage());
        $this->assertNull($preview->rawMime);
    }

    public function testSubjectReturnsTheBuiltSubject(): void
    {
        $this->assertSame('Your invoice', $this->previewer()->subject(PreviewWithAttachment::class));
    }

    public function testSubjectLetsABuildErrorThrow(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('preview blew up');

        $this->previewer()->subject(PreviewBroken::class);
    }

    public function testIsPreviewableAcceptsAPreviewableMailable(): void
    {
        $this->assertTrue($this->previewer()->isPreviewable(PreviewWelcome::class));
    }

    #[DataProvider('provideRejectedClassNames')]
    public function testIsPreviewableRejects(string $class): void
    {
        $this->assertFalse($this->previewer()->isPreviewable($class));
    }

    #[DataProvider('provideRejectedClassNames')]
    public function testRenderThrowsForARejectedClass(string $class): void
    {
        $this->expectException(PostalException::class);

        $this->previewer()->render($class);
    }

    #[DataProvider('provideRejectedClassNames')]
    public function testSubjectThrowsForARejectedClass(string $class): void
    {
        $this->expectException(PostalException::class);

        $this->previewer()->subject($class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideRejectedClassNames(): iterable
    {
        return [
            'unknown class'     => ['App\Mails\DoesNotExist'],
            'not a Mailable'    => [Postal::class],
            'not Previewable'   => [WelcomeEmail::class],
            'path traversal'    => ['App\\..\\..\\etc\\passwd'],
            'leading backslash' => ['\\' . PreviewWelcome::class],
            'empty string'      => [''],
        ];
    }
}
