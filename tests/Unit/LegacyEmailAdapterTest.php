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
use Config\Email as LegacyConfig;
use Myth\Postal\LegacyEmailAdapter;
use Myth\Postal\Transport\MailTransport;
use Myth\Postal\Transport\SendmailTransport;
use Myth\Postal\Transport\SmtpTransport;
use Tests\Support\RecordingTransport;

/**
 * @internal
 */
final class LegacyEmailAdapterTest extends CIUnitTestCase
{
    private function adapter(RecordingTransport $transport, ?LegacyConfig $config = null): LegacyEmailAdapter
    {
        return new LegacyEmailAdapter($config ?? new LegacyConfig(), $transport);
    }

    public function testSendsBasicTextMessageReturningBool(): void
    {
        $transport = new RecordingTransport();
        $adapter   = $this->adapter($transport);

        $result = $adapter
            ->setFrom('me@example.com', 'Me')
            ->setTo('you@example.com')
            ->setSubject('Hello')
            ->setMessage('Hi there')
            ->send();

        $this->assertTrue($result);
        $this->assertCount(1, $transport->sent);

        $email = $transport->sent[0];
        $this->assertSame('me@example.com', $email->from->email);
        $this->assertSame('Me', $email->from->name);
        $this->assertSame('you@example.com', $email->to[0]->email);
        $this->assertSame('Hello', $email->subject);
        $this->assertSame('Hi there', $email->textBody);
        $this->assertNull($email->htmlBody);
    }

    public function testSetMailTypeHtmlRoutesMessageToHtmlAndAltToText(): void
    {
        $transport = new RecordingTransport();

        $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setMailType('html')
            ->setMessage('<p>Rich</p>')
            ->setAltMessage('Plain alt')
            ->send();

        $email = $transport->sent[0];
        $this->assertSame('<p>Rich</p>', $email->htmlBody);
        $this->assertSame('Plain alt', $email->textBody);
    }

    public function testMailTypeOrderIndependence(): void
    {
        $transport = new RecordingTransport();

        // setMessage before setMailType must still land as HTML.
        $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setMessage('<p>Rich</p>')
            ->setMailType('html')
            ->send();

        $this->assertSame('<p>Rich</p>', $transport->sent[0]->htmlBody);
    }

    public function testSetFromReturnPathSetsEnvelopeSender(): void
    {
        $transport = new RecordingTransport();

        $this->adapter($transport)
            ->setFrom('me@example.com', 'Me', 'bounce@example.com')
            ->setTo('you@example.com')
            ->setMessage('Hi')
            ->send();

        $this->assertSame('bounce@example.com', $transport->sent[0]->returnPath);
    }

    public function testSetHeaderAndPriorityAndReplyToTakeEffect(): void
    {
        $transport = new RecordingTransport();

        $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setReplyTo('reply@example.com', 'Desk')
            ->setHeader('X-Campaign', 'spring')
            ->setPriority(1)
            ->setMessage('Hi')
            ->send();

        $email = $transport->sent[0];
        $this->assertSame('spring', $email->headers['X-Campaign']);
        $this->assertSame(1, $email->priority);
        $this->assertSame('reply@example.com', $email->replyTo->email);
    }

    public function testSetCcAndBcc(): void
    {
        $transport = new RecordingTransport();

        $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setCC('cc@example.com')
            ->setBCC('bcc@example.com')
            ->setMessage('Hi')
            ->send();

        $email = $transport->sent[0];
        $this->assertSame('cc@example.com', $email->cc[0]->email);
        $this->assertSame('bcc@example.com', $email->bcc[0]->email);
    }

    public function testCommaSeparatedRecipientsAreSplit(): void
    {
        $transport = new RecordingTransport();

        $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('a@example.com, b@example.com')
            ->setMessage('Hi')
            ->send();

        $emails = array_map(static fn ($a) => $a->email, $transport->sent[0]->to);
        $this->assertSame(['a@example.com', 'b@example.com'], $emails);
    }

    public function testSetWordWrapTakesEffect(): void
    {
        $transport = new RecordingTransport();

        $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setWordWrap(true)
            ->setMessage('Hi')
            ->send();

        $this->assertTrue($transport->sent[0]->wordWrap);
    }

    public function testSendFailsWhenNoFrom(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport)
            ->setTo('you@example.com')
            ->setMessage('Hi');

        $this->assertFalse($adapter->send());
        $this->assertCount(0, $transport->sent);
        $this->assertStringContainsStringIgnoringCase('from', $adapter->printDebugger());
    }

    public function testSendFailsWhenNoRecipients(): void
    {
        $transport = new RecordingTransport();

        $result = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setMessage('Hi')
            ->send();

        $this->assertFalse($result);
        $this->assertCount(0, $transport->sent);
    }

    public function testInvalidAddressIsSwallowedAndCausesFalseWithoutThrowing(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('not-an-email')
            ->setMessage('Hi');

        $result = $adapter->send();

        $this->assertFalse($result);
        $this->assertCount(0, $transport->sent);
        $this->assertStringContainsStringIgnoringCase('invalid', $adapter->printDebugger());
    }

    public function testFailedTransportSurfacesErrorInDebugger(): void
    {
        $transport = new RecordingTransport(false, 'boom');

        $adapter = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setMessage('Hi');

        $this->assertFalse($adapter->send());
        $this->assertStringContainsString('boom', $adapter->printDebugger());
    }

    public function testPrintDebuggerRendersHeadersSubjectAndBody(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setSubject('Hello')
            ->setMessage('Body text here');

        $debug = $adapter->printDebugger();

        $this->assertStringContainsString('From: me@example.com', $debug);
        $this->assertStringContainsString('Hello', $debug);
        $this->assertStringContainsString('Body text here', $debug);
        $this->assertStringContainsString('<pre>', $debug);
    }

    public function testPrintDebuggerHonorsIncludeSelection(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setSubject('Hello')
            ->setMessage('Body text here');

        $bodyOnly = $adapter->printDebugger(['subject']);

        $this->assertStringContainsString('Hello', $bodyOnly);
        $this->assertStringNotContainsString('From: me@example.com', $bodyOnly);
    }

    public function testBccBatchModeLoopsInChunksAndAndsResults(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setBCC('a@x.com, b@x.com, c@x.com, d@x.com, e@x.com', 2)
            ->setMessage('Hi');

        $result = $adapter->send();

        $this->assertTrue($result);
        // 5 BCCs in batches of 2 → 3 sends.
        $this->assertCount(3, $transport->sent);
        $this->assertCount(2, $transport->sent[0]->bcc);
        $this->assertCount(2, $transport->sent[1]->bcc);
        $this->assertCount(1, $transport->sent[2]->bcc);
    }

    public function testBccBatchResultIsAndOfBatches(): void
    {
        $transport = new RecordingTransport(false, 'nope');

        $result = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setBCC('a@x.com, b@x.com, c@x.com', 2)
            ->setMessage('Hi')
            ->send();

        $this->assertFalse($result);
        $this->assertCount(2, $transport->sent);
    }

    public function testAttachIsStubbedAndSendFailsWithDebuggerNote(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setMessage('Hi');

        $adapter->attach('/tmp/file.pdf');

        $this->assertFalse($adapter->send());
        $this->assertCount(0, $transport->sent);
        $this->assertStringContainsStringIgnoringCase('attachment', $adapter->printDebugger());
    }

    public function testSetAttachmentCidReturnsAGeneratedCid(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport)->attach('/tmp/logo.png');
        $cid     = $adapter->setAttachmentCID('/tmp/logo.png');

        $this->assertIsString($cid);
        $this->assertNotSame('', $cid);
    }

    public function testClearResetsMessageState(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setSubject('Hello')
            ->setMessage('Hi');

        $adapter->send();
        $adapter->clear();

        // After clear, a send with no new recipients fails.
        $this->assertFalse($adapter->setFrom('me@example.com')->setMessage('Again')->send());
    }

    public function testInitializeMergesConfig(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport);
        $adapter->initialize(['mailType' => 'html']);

        $adapter
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setMessage('<p>Hi</p>')
            ->send();

        $this->assertSame('<p>Hi</p>', $transport->sent[0]->htmlBody);
    }

    public function testValidationHelpers(): void
    {
        $adapter = $this->adapter(new RecordingTransport());

        $this->assertTrue($adapter->isValidEmail('good@example.com'));
        $this->assertFalse($adapter->isValidEmail('bad'));
        $this->assertTrue($adapter->validateEmail(['a@b.com', 'c@d.com']));
        $this->assertFalse($adapter->validateEmail(['a@b.com', 'nope']));
        $this->assertSame('a@b.com', $adapter->cleanEmail('John <a@b.com>'));
    }

    public function testSetProtocolSelectsSmtpTransportFromLegacyConfig(): void
    {
        $config           = new LegacyConfig();
        $config->protocol = 'smtp';
        $config->SMTPHost = 'smtp.example.com';
        $config->SMTPPort = 2525;
        $config->SMTPUser = 'user';
        $config->SMTPPass = 'pass';

        // No transport override: the adapter must resolve one from the protocol.
        $adapter   = new LegacyEmailAdapter($config);
        $transport = $this->getPrivateMethodInvoker($adapter, 'resolveTransport')();

        $this->assertInstanceOf(SmtpTransport::class, $transport);
    }

    public function testSetProtocolSelectsSendmailTransport(): void
    {
        $adapter = new LegacyEmailAdapter();
        $adapter->setProtocol('sendmail');

        $this->assertInstanceOf(
            SendmailTransport::class,
            $this->getPrivateMethodInvoker($adapter, 'resolveTransport')(),
        );
    }

    public function testSetProtocolSelectsMailTransport(): void
    {
        $adapter = new LegacyEmailAdapter();
        $adapter->setProtocol('mail');

        $this->assertInstanceOf(
            MailTransport::class,
            $this->getPrivateMethodInvoker($adapter, 'resolveTransport')(),
        );
    }

    public function testPrintDebuggerEscapesUserInputInErrorLines(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('<script>alert(1)</script>')
            ->setMessage('Hi');

        $adapter->send();
        $debug = $adapter->printDebugger();

        // The malicious address must not survive as live markup in the output.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $debug);
        $this->assertStringContainsString('&lt;script&gt;', $debug);
    }

    public function testRetainsLastResultAndEmail(): void
    {
        $transport = new RecordingTransport();

        $adapter = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setSubject('Hello')
            ->setMessage('Hi');

        $this->assertNull($adapter->lastResult());

        $adapter->send(false);

        $this->assertNotNull($adapter->lastResult());
        $this->assertTrue($adapter->lastResult()->success);
        $this->assertSame('Hello', $adapter->lastEmail()->subject);
    }

    public function testSetNewlineAndSetCrlfAreAcceptedAsNoOps(): void
    {
        $transport = new RecordingTransport();

        $result = $this->adapter($transport)
            ->setFrom('me@example.com')
            ->setTo('you@example.com')
            ->setNewline("\r\n")
            ->setCRLF("\r\n")
            ->setMessage('Hi')
            ->send();

        $this->assertTrue($result);
    }
}
