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

namespace Tests\Support\Mailables;

use Myth\Postal\Mailable;
use Myth\Postal\Previewable;

final class PreviewWithAttachment extends Mailable implements Previewable
{
    public static function previewInstance(): static
    {
        return new self();
    }

    protected function build(): void
    {
        $this->from('me@example.com')
            ->to('you@example.com')
            ->subject('Your invoice')
            ->html('<p>Invoice attached</p>')
            ->text('Invoice attached');

        $this->email->attachData('pdf-bytes', 'invoice.pdf', 'application/pdf');
    }
}
