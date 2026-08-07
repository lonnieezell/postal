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

final class MarkdownWelcome extends Mailable
{
    public function __construct(private readonly string $name = 'World')
    {
        parent::__construct();
    }

    protected function build(): void
    {
        $this->from('me@example.com')
            ->to('you@example.com')
            ->subject('Welcome')
            ->markdown('Tests\Views\markdown\welcome', ['name' => $this->name]);
    }
}
