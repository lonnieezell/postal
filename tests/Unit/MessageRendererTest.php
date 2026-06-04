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
use Myth\Postal\Email;
use Myth\Postal\MessageRenderer;

/**
 * @internal
 */
final class MessageRendererTest extends CIUnitTestCase
{
    public function testRendersTextOnlyMessage(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->subject('Hello')
            ->text('Hello there');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('From: me@example.com', $mime);
        $this->assertStringContainsString('To: you@example.com', $mime);
        $this->assertStringContainsString('Subject: Hello', $mime);
        $this->assertStringContainsString('MIME-Version: 1.0', $mime);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $mime);

        // Body follows a blank line that separates headers from content.
        [, $body] = explode("\r\n\r\n", $mime, 2);
        $this->assertStringContainsString('Hello there', $body);
    }

    public function testRendersCcAndReplyToWhenSet(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->cc('boss@example.com')
            ->replyTo('reply@example.com', 'Reply Desk')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('Cc: boss@example.com', $mime);
        $this->assertStringContainsString('Reply-To: Reply Desk <reply@example.com>', $mime);
    }

    public function testOmitsCcAndReplyToWhenUnset(): void
    {
        $email = (new Email())->from('me@example.com')->to('you@example.com')->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringNotContainsString('Cc:', $mime);
        $this->assertStringNotContainsString('Reply-To:', $mime);
    }

    public function testEmitsArbitraryCustomHeaders(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->header('X-Campaign', 'spring')
            ->header('X-Mailer', 'Postal')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('X-Campaign: spring', $mime);
        $this->assertStringContainsString('X-Mailer: Postal', $mime);
    }

    public function testReturnPathEmitsReturnPathAndSender(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->returnPath('bounce@example.com')
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('Return-Path: <bounce@example.com>', $mime);
        $this->assertStringContainsString('Sender: bounce@example.com', $mime);
    }

    public function testPriorityEmitsXPriorityHeader(): void
    {
        $email = (new Email())
            ->from('me@example.com')
            ->to('you@example.com')
            ->priority(1)
            ->text('Hi');

        $mime = (new MessageRenderer())->render($email);

        $this->assertStringContainsString('X-Priority: 1 (Highest)', $mime);
    }

    public function testDefaultPriorityIsNotEmitted(): void
    {
        $email = (new Email())->from('me@example.com')->to('you@example.com')->text('Hi');

        $this->assertStringNotContainsString('X-Priority:', (new MessageRenderer())->render($email));
    }
}
