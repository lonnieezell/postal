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
use Myth\Postal\Address;
use Myth\Postal\Email;

/**
 * @internal
 */
final class EmailTest extends CIUnitTestCase
{
    public function testBuilderMethodsReturnSameInstance(): void
    {
        $email = new Email();

        $this->assertSame($email, $email->from('me@example.com'));
        $this->assertSame($email, $email->to('you@example.com'));
        $this->assertSame($email, $email->subject('Hi'));
    }

    public function testFromAndReplyToAreAddresses(): void
    {
        $email = (new Email())
            ->from('me@example.com', 'Me')
            ->replyTo('reply@example.com', 'Reply');

        $this->assertInstanceOf(Address::class, $email->from);
        $this->assertSame('me@example.com', $email->from->email);
        $this->assertSame('Me', $email->from->name);
        $this->assertInstanceOf(Address::class, $email->replyTo);
        $this->assertSame('reply@example.com', $email->replyTo->email);
    }

    public function testRecipientsAccumulate(): void
    {
        $email = (new Email())
            ->to('a@example.com')
            ->to('Bee <b@example.com>')
            ->cc('c@example.com')
            ->bcc('d@example.com');

        $this->assertCount(2, $email->to);
        $this->assertSame('a@example.com', $email->to[0]->email);
        $this->assertSame('b@example.com', $email->to[1]->email);
        $this->assertSame('Bee', $email->to[1]->name);
        $this->assertCount(1, $email->cc);
        $this->assertSame('c@example.com', $email->cc[0]->email);
        $this->assertCount(1, $email->bcc);
        $this->assertSame('d@example.com', $email->bcc[0]->email);
    }

    public function testToAcceptsArrayOfAddresses(): void
    {
        $email = (new Email())->to(['a@example.com', 'Bee <b@example.com>']);

        $this->assertCount(2, $email->to);
        $this->assertSame('a@example.com', $email->to[0]->email);
        $this->assertSame('Bee', $email->to[1]->name);
    }

    public function testSubjectHtmlAndText(): void
    {
        $email = (new Email())
            ->subject('Welcome')
            ->html('<p>Hi</p>')
            ->text('Hi');

        $this->assertSame('Welcome', $email->subject);
        $this->assertSame('<p>Hi</p>', $email->htmlBody);
        $this->assertSame('Hi', $email->textBody);
    }

    public function testHeaderAccumulatesArbitraryHeaders(): void
    {
        $email = (new Email())
            ->header('X-Campaign', 'spring')
            ->header('X-Mailer', 'Postal');

        $this->assertSame([
            'X-Campaign' => 'spring',
            'X-Mailer'   => 'Postal',
        ], $email->headers);
    }

    public function testPriorityDefaultsToThree(): void
    {
        $this->assertSame(3, (new Email())->priority);
    }

    public function testPrioritySetsLevel(): void
    {
        $this->assertSame(1, (new Email())->priority(1)->priority);
    }

    public function testReturnPathSetsEnvelopeSender(): void
    {
        $email = (new Email())->returnPath('bounce@example.com');

        $this->assertSame('bounce@example.com', $email->returnPath);
    }

    public function testAttachRecordsPathBasedAttachment(): void
    {
        $email = (new Email())->attach('/var/data/report.pdf');

        $this->assertCount(1, $email->attachments);
        $this->assertSame('report.pdf', $email->attachments[0]->name);
        $this->assertSame('attachment', $email->attachments[0]->disposition);
        $this->assertNull($email->attachments[0]->cid);
    }

    public function testAttachDataRecordsBytes(): void
    {
        $email = (new Email())->attachData('raw', 'note.txt', 'text/plain');

        $this->assertCount(1, $email->attachments);
        $this->assertSame('note.txt', $email->attachments[0]->name);
        $this->assertSame('raw', $email->attachments[0]->content());
        $this->assertSame('text/plain', $email->attachments[0]->mimeType());
    }

    public function testEmbedImageRecordsInlineAttachmentWithCid(): void
    {
        $email = (new Email())->embedImage('/var/img/logo.png', 'logo123');

        $this->assertCount(1, $email->attachments);
        $this->assertSame('inline', $email->attachments[0]->disposition);
        $this->assertSame('logo123', $email->attachments[0]->cid);
        $this->assertSame('logo.png', $email->attachments[0]->name);
    }

    public function testAttachmentsAccumulateAndChain(): void
    {
        $email = new Email();

        $this->assertSame($email, $email->attach('/a.pdf'));
        $this->assertSame($email, $email->attachData('x', 'b.txt'));
        $this->assertSame($email, $email->embedImage('/c.png', 'cid1'));

        $this->assertCount(3, $email->attachments);
    }
}
