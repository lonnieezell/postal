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
use Myth\Postal\Transport\SmtpTransport;
use Tests\Support\FakeSocket;

/**
 * @internal
 */
final class SmtpTransportTest extends CIUnitTestCase
{
    public function testSendsPlainTextMessageEndToEnd(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250 mail.example.com\r\n", // EHLO
            "250 OK\r\n",               // MAIL FROM
            "250 OK\r\n",               // RCPT TO
            "354 End data\r\n",         // DATA
            "250 2.0.0 Ok: queued\r\n", // body
            "221 Bye\r\n",              // QUIT
        ]);

        $result = $this->transport($socket)->send($this->message());

        $this->assertTrue($result->success, $result->error ?? '');

        $transcript = $socket->transcript();
        $this->assertStringContainsString('EHLO ', $transcript);
        $this->assertStringContainsString('MAIL FROM:<me@example.com>', $transcript);
        $this->assertStringContainsString('RCPT TO:<you@example.com>', $transcript);
        $this->assertStringContainsString("DATA\r\n", $transcript);
        $this->assertStringContainsString('Subject: Hi', $transcript);
        $this->assertStringContainsString("\r\n.\r\n", $transcript);
        $this->assertStringContainsString("QUIT\r\n", $transcript);
    }

    public function testReturnsMessageIdFromRenderedHeaders(): void
    {
        $result = $this->transport($this->okSocket())->send($this->message());

        $this->assertNotNull($result->messageId);
        $this->assertStringContainsString('@example.com', $result->messageId);
    }

    public function testParsesMultilineEhloAndAuthsWithLogin(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250-mail.example.com greets you\r\n",
            "250-AUTH LOGIN PLAIN\r\n",
            "250 STARTTLS\r\n",
            "334 VXNlcm5hbWU6\r\n", // AUTH LOGIN -> username challenge
            "334 UGFzc3dvcmQ6\r\n", // password challenge
            "235 2.7.0 Authentication successful\r\n",
            "250 OK\r\n", // MAIL
            "250 OK\r\n", // RCPT
            "354 go\r\n",
            "250 queued\r\n",
            "221 Bye\r\n",
        ]);

        $result = $this->transport($socket, [
            'authType' => 'login',
            'username' => 'user',
            'password' => 'pass',
        ])->send($this->message());

        $this->assertTrue($result->success, $result->error ?? '');
        $transcript = $socket->transcript();
        $this->assertStringContainsString("AUTH LOGIN\r\n", $transcript);
        $this->assertStringContainsString(base64_encode('user') . "\r\n", $transcript);
        $this->assertStringContainsString(base64_encode('pass') . "\r\n", $transcript);
    }

    public function testAuthsWithPlain(): void
    {
        $socket = $this->authSocket();

        $result = $this->transport($socket, [
            'authType' => 'plain',
            'username' => 'user',
            'password' => 'pass',
        ])->send($this->message());

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertStringContainsString(
            'AUTH PLAIN ' . base64_encode("\0user\0pass"),
            $socket->transcript(),
        );
    }

    public function testAuthsWithXoauth2(): void
    {
        $socket = $this->authSocket();

        $result = $this->transport($socket, [
            'authType' => 'xoauth2',
            'username' => 'user@example.com',
            'password' => 'token123',
        ])->send($this->message());

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertStringContainsString(
            'AUTH XOAUTH2 ' . base64_encode("user=user@example.com\1auth=Bearer token123\1\1"),
            $socket->transcript(),
        );
    }

    public function testAuthFailureReturnsFailedResult(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250 mail.example.com\r\n",
            "334 VXNlcm5hbWU6\r\n",
            "334 UGFzc3dvcmQ6\r\n",
            "535 5.7.8 Authentication credentials invalid\r\n",
        ]);

        $result = $this->transport($socket, [
            'authType' => 'login',
            'username' => 'user',
            'password' => 'wrong',
        ])->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('535', (string) $result->error);
        // The base64 credential must never appear in the error (it would be logged).
        $this->assertStringNotContainsString(base64_encode('wrong'), (string) $result->error);
    }

    public function testStartTlsUpgradesTheConnection(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250-mail.example.com\r\n",
            "250 STARTTLS\r\n",
            "220 2.0.0 Ready to start TLS\r\n", // STARTTLS
            "250 mail.example.com\r\n",         // second EHLO
            "250 OK\r\n",
            "250 OK\r\n",
            "354 go\r\n",
            "250 queued\r\n",
            "221 Bye\r\n",
        ]);

        $result = $this->transport($socket, ['encryption' => 'tls'])->send($this->message());

        $this->assertTrue($result->success, $result->error ?? '');
        $this->assertCount(1, $socket->cryptoCalls);
        $this->assertTrue($socket->cryptoCalls[0][0]);
        $this->assertSame(2, substr_count($socket->transcript(), 'EHLO '));
        $this->assertStringContainsString("STARTTLS\r\n", $socket->transcript());
    }

    public function testImplicitSslPrefixesTheHost(): void
    {
        $socket = $this->okSocket();

        $this->transport($socket, ['encryption' => 'ssl'])->send($this->message());

        $this->assertSame('ssl://mail.example.com', $socket->connections[0][0]);
    }

    public function testPort465DefaultsToImplicitTls(): void
    {
        $socket = $this->okSocket();

        // No encryption set, but port 465 means implicit TLS (RFC 8314).
        $this->transport($socket, ['port' => 465])->send($this->message());

        $this->assertSame('ssl://mail.example.com', $socket->connections[0][0]);
        $this->assertStringNotContainsString('STARTTLS', $socket->transcript());
    }

    public function testEhloUsesConfiguredHeloName(): void
    {
        $socket = $this->okSocket();

        $this->transport($socket, ['helo' => 'mail.app.test'])->send($this->message());

        $this->assertStringContainsString("EHLO mail.app.test\r\n", $socket->transcript());
    }

    public function testEhloDefaultsToTheSystemHostname(): void
    {
        $socket = $this->okSocket();

        $hostname = gethostname();
        $expected = $hostname !== false ? $hostname : 'localhost';

        // The transport() helper sets no helo, so the default applies.
        $this->transport($socket)->send($this->message());

        $this->assertStringContainsString("EHLO {$expected}\r\n", $socket->transcript());
    }

    public function testKeepAliveReusesTheConnectionAcrossSends(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250 mail.example.com\r\n", // EHLO (once)
            "250 OK\r\n",               // send 1: MAIL
            "250 OK\r\n",               // send 1: RCPT
            "354 go\r\n",
            "250 queued\r\n",
            "250 OK\r\n",               // send 2: RSET
            "250 OK\r\n",               // send 2: MAIL
            "250 OK\r\n",               // send 2: RCPT
            "354 go\r\n",
            "250 queued\r\n",
        ]);

        $transport = $this->transport($socket, ['keepAlive' => true]);
        $this->assertTrue($transport->send($this->message())->success);
        $this->assertTrue($transport->send($this->message())->success);

        $this->assertCount(1, $socket->connections);
        $this->assertSame(1, substr_count($socket->transcript(), 'EHLO '));
        $this->assertSame(1, substr_count($socket->transcript(), "RSET\r\n"));
        $this->assertStringNotContainsString("QUIT\r\n", $socket->transcript());
    }

    public function testKeepAliveReopensAStaleConnection(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250 mail.example.com\r\n", // EHLO (first connection)
            "250 OK\r\n",               // send 1: MAIL
            "250 OK\r\n",               // send 1: RCPT
            "354 go\r\n",
            "250 queued\r\n",
            "421 idle timeout\r\n",     // send 2: RSET rejected -> stale
            "220 mail.example.com ESMTP\r\n", // reconnect greeting
            "250 mail.example.com\r\n", // reconnect EHLO
            "250 OK\r\n",               // send 2: MAIL
            "250 OK\r\n",               // send 2: RCPT
            "354 go\r\n",
            "250 queued\r\n",
        ]);

        $transport = $this->transport($socket, ['keepAlive' => true]);
        $this->assertTrue($transport->send($this->message())->success);
        $this->assertTrue($transport->send($this->message())->success, 'second send should reconnect');

        $this->assertCount(2, $socket->connections);
    }

    public function testSendsToCcAndBccRecipients(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250 mail.example.com\r\n",
            "250 OK\r\n", // MAIL
            "250 OK\r\n", // RCPT to
            "250 OK\r\n", // RCPT cc
            "250 OK\r\n", // RCPT bcc
            "354 go\r\n",
            "250 queued\r\n",
            "221 Bye\r\n",
        ]);

        $email = $this->message()
            ->cc('cc@example.com')
            ->bcc('bcc@example.com');

        $this->assertTrue($this->transport($socket)->send($email)->success);

        $transcript = $socket->transcript();
        $this->assertStringContainsString('RCPT TO:<you@example.com>', $transcript);
        $this->assertStringContainsString('RCPT TO:<cc@example.com>', $transcript);
        $this->assertStringContainsString('RCPT TO:<bcc@example.com>', $transcript);
        // Bcc must never appear as a header in the DATA payload.
        $this->assertStringNotContainsString('Bcc:', $transcript);
    }

    public function testStripsCrlfFromEnvelopeAddressesToPreventInjection(): void
    {
        $socket = $this->okSocket();

        // A recipient address carrying CRLF must not inject a second command.
        $email = $this->message()->to("victim@example.com>\r\nRSET\r\nMAIL FROM:<evil@example.com");
        $this->transport($socket)->send($email);

        $transcript = $socket->transcript();
        $this->assertStringNotContainsString("RSET\r\n", $transcript);
        // The CRLF is removed, collapsing the payload onto the single RCPT line.
        $this->assertStringContainsString(
            "RCPT TO:<victim@example.com>RSETMAIL FROM:<evil@example.com>\r\n",
            $transcript,
        );
    }

    public function testUsesReturnPathAsEnvelopeSender(): void
    {
        $socket = $this->okSocket();

        $email = $this->message()->returnPath('bounce@example.com');
        $this->transport($socket)->send($email);

        $this->assertStringContainsString('MAIL FROM:<bounce@example.com>', $socket->transcript());
    }

    public function testRecipientRejectionReturnsFailedResult(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250 mail.example.com\r\n",
            "250 OK\r\n",                       // MAIL
            "550 5.1.1 No such user here\r\n",  // RCPT
        ]);

        $result = $this->transport($socket)->send($this->message());

        $this->assertFalse($result->success);
        $this->assertStringContainsString('550', (string) $result->error);
    }

    public function testDroppedConnectionReturnsFailedResult(): void
    {
        // An empty script makes the first read throw (server hung up).
        $result = $this->transport(new FakeSocket([]))->send($this->message());

        $this->assertFalse($result->success);
        $this->assertNotNull($result->error);
    }

    public function testDsnParametersAppendedWhenServerSupportsThem(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250-mail.example.com\r\n",
            "250 DSN\r\n",
            "250 OK\r\n", // MAIL
            "250 OK\r\n", // RCPT
            "354 go\r\n",
            "250 queued\r\n",
            "221 Bye\r\n",
        ]);

        $this->transport($socket, [
            'dsn' => ['notify' => 'FAILURE,DELAY', 'ret' => 'HDRS', 'envid' => 'abc123'],
        ])->send($this->message());

        $transcript = $socket->transcript();
        $this->assertStringContainsString('MAIL FROM:<me@example.com> RET=HDRS ENVID=abc123', $transcript);
        $this->assertStringContainsString('NOTIFY=FAILURE,DELAY ORCPT=rfc822;you@example.com', $transcript);
    }

    public function testDsnParametersOmittedWhenServerLacksSupport(): void
    {
        $socket = $this->okSocket();

        $this->transport($socket, ['dsn' => ['notify' => 'FAILURE']])->send($this->message());

        $this->assertStringNotContainsString('NOTIFY=', $socket->transcript());
    }

    public function testDotStuffsLinesBeginningWithADot(): void
    {
        $socket = $this->okSocket();

        $email = $this->message()->text("Normal line\r\n. dot leads\r\nlast");
        $this->transport($socket)->send($email);

        // The bare "." line must be escaped to ".." inside DATA.
        $this->assertStringContainsString("\r\n.. dot leads\r\n", $socket->transcript());
    }

    public function testNoisyQuitDoesNotFailADeliveredMessage(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250 mail.example.com\r\n",
            "250 OK\r\n",     // MAIL
            "250 OK\r\n",     // RCPT
            "354 go\r\n",
            "250 queued\r\n", // body accepted -> delivered
            "451 try later\r\n", // QUIT botched by the server
        ]);

        $this->assertTrue($this->transport($socket)->send($this->message())->success);
    }

    public function testPingReturnsTrueOnSuccessfulHandshake(): void
    {
        $socket = new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250 mail.example.com\r\n",
            "221 Bye\r\n",
        ]);

        $this->assertTrue($this->transport($socket)->ping());
    }

    public function testPingReturnsFalseWhenHandshakeFails(): void
    {
        $socket = new FakeSocket(["554 Service unavailable\r\n"]);

        $this->assertFalse($this->transport($socket)->ping());
    }

    /**
     * A complete happy-path server script (no auth, no TLS).
     */
    private function okSocket(): FakeSocket
    {
        return new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250 mail.example.com\r\n",
            "250 OK\r\n", // MAIL
            "250 OK\r\n", // RCPT
            "354 go\r\n",
            "250 queued\r\n",
            "221 Bye\r\n",
        ]);
    }

    /**
     * A server script that accepts auth then the transaction.
     */
    private function authSocket(): FakeSocket
    {
        return new FakeSocket([
            "220 mail.example.com ESMTP\r\n",
            "250 mail.example.com\r\n",
            "235 2.7.0 Authentication successful\r\n",
            "250 OK\r\n", // MAIL
            "250 OK\r\n", // RCPT
            "354 go\r\n",
            "250 queued\r\n",
            "221 Bye\r\n",
        ]);
    }

    private function message(): Email
    {
        return (new Email())
            ->from('me@example.com', 'Me')
            ->to('you@example.com')
            ->subject('Hi')
            ->text('Hello there');
    }

    /**
     * Builds an SmtpTransport bound to the given scripted socket.
     *
     * @param array<string, mixed> $settings
     */
    private function transport(FakeSocket $socket, array $settings = []): SmtpTransport
    {
        return new SmtpTransport([
            'host' => 'mail.example.com',
            'port' => 25,
            ...$settings,
        ], $socket);
    }
}
