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

namespace Tests\Integration;

use CodeIgniter\Test\CIUnitTestCase;
use Myth\Postal\Email;
use Myth\Postal\Transport\SmtpTransport;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises SmtpTransport against a real MailHog server over the wire. Skipped
 * automatically when no MailHog is reachable, so the suite stays green without
 * one. Point it at a server with MAILHOG_HOST / MAILHOG_SMTP_PORT /
 * MAILHOG_API_PORT.
 *
 * @internal
 */
#[Group('integration')]
final class SmtpMailHogTest extends CIUnitTestCase
{
    private string $host;
    private int $smtpPort;
    private int $apiPort;

    protected function setUp(): void
    {
        parent::setUp();

        $this->host     = getenv('MAILHOG_HOST') ?: '127.0.0.1';
        $this->smtpPort = (int) (getenv('MAILHOG_SMTP_PORT') ?: 1025);
        $this->apiPort  = (int) (getenv('MAILHOG_API_PORT') ?: 8025);

        $probe = @fsockopen($this->host, $this->smtpPort, $errno, $errstr, 1);

        if ($probe === false) {
            $this->markTestSkipped("No MailHog at {$this->host}:{$this->smtpPort}");
        }

        fclose($probe);
    }

    public function testDeliversMessageToMailHog(): void
    {
        $this->clearMailHog();

        $subject = 'Postal integration ' . bin2hex(random_bytes(6));

        $email = (new Email())
            ->from('sender@example.com', 'Postal')
            ->to('recipient@example.com')
            ->subject($subject)
            ->html('<p>Wire test</p>')
            ->text('Wire test');

        $transport = new SmtpTransport([
            'host'    => $this->host,
            'port'    => $this->smtpPort,
            'timeout' => 5,
        ]);

        $result = $transport->send($email);

        $this->assertTrue($result->success, $result->error ?? '');

        $messages = $this->fetchMailHogMessages();
        $subjects = array_map(
            static fn (array $item): string => $item['Content']['Headers']['Subject'][0] ?? '',
            $messages['items'] ?? [],
        );

        $this->assertContains($subject, $subjects);
    }

    public function testKeepAliveDeliversMultipleMessagesOnOneConnection(): void
    {
        $this->clearMailHog();

        $transport = new SmtpTransport([
            'host'      => $this->host,
            'port'      => $this->smtpPort,
            'timeout'   => 5,
            'keepAlive' => true,
        ]);

        foreach (['first', 'second'] as $body) {
            $email = (new Email())
                ->from('sender@example.com')
                ->to('recipient@example.com')
                ->subject('keepalive')
                ->text($body);

            $this->assertTrue($transport->send($email)->success);
        }

        $this->assertSame(2, $this->fetchMailHogMessages()['total'] ?? 0);
    }

    private function clearMailHog(): void
    {
        $context = stream_context_create(['http' => ['method' => 'DELETE', 'timeout' => 5]]);
        @file_get_contents("http://{$this->host}:{$this->apiPort}/api/v1/messages", false, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchMailHogMessages(): array
    {
        $json = (string) @file_get_contents("http://{$this->host}:{$this->apiPort}/api/v2/messages");

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true) ?: [];

        return $decoded;
    }
}
