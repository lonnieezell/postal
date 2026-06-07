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
use Myth\Postal\Exceptions\PostalException;
use Myth\Postal\Transport\DkimSigningTransport;
use Tests\Support\RawRecordingTransport;

/**
 * @internal
 */
final class DkimSigningTransportTest extends CIUnitTestCase
{
    private const KEY_DIR = __DIR__ . '/../_support/dkim';

    public function testSignsAndDelegatesToInnerTransport(): void
    {
        $inner  = new RawRecordingTransport();
        $result = $this->transport($inner)->send($this->message());

        $this->assertTrue($result->success);
        $this->assertInstanceOf(Email::class, $inner->lastEmail);
        // The inner transport receives the pre-signed bytes via rawMessage.
        $this->assertNotNull($inner->lastEmail->rawMessage);
        $this->assertStringStartsWith('DKIM-Signature:', $inner->lastEmail->rawMessage);
    }

    public function testSignatureCarriesTheConfiguredDomainAndSelector(): void
    {
        $inner = new RawRecordingTransport();
        $this->transport($inner)->send($this->message());

        $tags = $this->signatureTags((string) $inner->lastEmail->rawMessage);

        $this->assertSame('example.com', $tags['d']);
        $this->assertSame('postal', $tags['s']);
        $this->assertSame('rsa-sha256', $tags['a']);
        $this->assertSame('relaxed/relaxed', $tags['c']);
    }

    public function testBodyHashMatchesTheCanonicalBody(): void
    {
        $inner = new RawRecordingTransport();
        $this->transport($inner)->send($this->message());

        $tags = $this->signatureTags((string) $inner->lastEmail->rawMessage);

        // The text body "Hello DKIM signing" canonicalises (relaxed) to itself
        // plus a single trailing CRLF — computed here independently of the signer.
        $expected = base64_encode(hash('sha256', "Hello DKIM signing\r\n", true));
        $this->assertSame($expected, $tags['bh']);
    }

    public function testSignatureVerifiesWithFixturePublicKey(): void
    {
        $inner = new RawRecordingTransport();
        $this->transport($inner)->send($this->message());

        $signed = (string) $inner->lastEmail->rawMessage;
        $tags   = $this->signatureTags($signed);

        [$headerBlock] = explode("\r\n\r\n", $signed, 2);
        $lines         = explode("\r\n", $headerBlock);
        $dkimLine      = array_shift($lines);
        $dkimValue     = trim(substr((string) $dkimLine, strlen('DKIM-Signature:')));

        // Rebuild the relaxed signing input: each signed header, then the
        // DKIM-Signature header itself with an empty b= and no trailing CRLF.
        $input = '';

        foreach (explode(':', $tags['h']) as $name) {
            $input .= $this->relaxHeader($name, $this->findHeader($lines, $name)) . "\r\n";
        }

        $emptied = (string) preg_replace('/b=[A-Za-z0-9+\/=]*$/', 'b=', $dkimValue);
        $input .= 'dkim-signature:' . $this->relaxValue($emptied);

        $verified = openssl_verify(
            $input,
            (string) base64_decode($tags['b'], true),
            (string) file_get_contents(self::KEY_DIR . '/public.pem'),
            OPENSSL_ALGO_SHA256,
        );

        $this->assertSame(1, $verified, 'DKIM signature did not verify against the fixture public key.');
    }

    public function testSignsFromHeaderAtMinimum(): void
    {
        $inner = new RawRecordingTransport();
        $this->transport($inner)->send($this->message());

        $tags = $this->signatureTags((string) $inner->lastEmail->rawMessage);

        $this->assertStringContainsStringIgnoringCase('from', $tags['h']);
    }

    public function testAcceptsAPrivateKeyFilePath(): void
    {
        $inner     = new RawRecordingTransport();
        $transport = new DkimSigningTransport($inner, [
            'domain'     => 'example.com',
            'selector'   => 'postal',
            'privateKey' => self::KEY_DIR . '/private.pem',
        ]);

        $this->assertTrue($transport->send($this->message())->success);
        $this->assertStringStartsWith('DKIM-Signature:', (string) $inner->lastEmail->rawMessage);
    }

    public function testRejectsAnInvalidPrivateKey(): void
    {
        $this->expectException(PostalException::class);

        new DkimSigningTransport(new RawRecordingTransport(), [
            'domain'     => 'example.com',
            'selector'   => 'postal',
            'privateKey' => 'not a key',
        ]);
    }

    public function testRejectsMissingDomain(): void
    {
        $this->expectException(PostalException::class);

        new DkimSigningTransport(new RawRecordingTransport(), [
            'selector'   => 'postal',
            'privateKey' => self::KEY_DIR . '/private.pem',
        ]);
    }

    public function testPingDelegatesToInner(): void
    {
        $this->assertTrue($this->transport(new RawRecordingTransport())->ping());
    }

    public function testOriginalEmailIsNotMutated(): void
    {
        $email = $this->message();
        $this->transport(new RawRecordingTransport())->send($email);

        // Signing works on a clone; the caller's Email keeps rawMessage null.
        $this->assertNull($email->rawMessage);
    }

    private function message(): Email
    {
        return (new Email())
            ->from('me@example.com', 'Me')
            ->to('you@example.com')
            ->subject('Hi')
            ->text('Hello DKIM signing');
    }

    private function transport(RawRecordingTransport $inner): DkimSigningTransport
    {
        return new DkimSigningTransport($inner, [
            'domain'     => 'example.com',
            'selector'   => 'postal',
            'privateKey' => (string) file_get_contents(self::KEY_DIR . '/private.pem'),
        ]);
    }

    /**
     * Parses the DKIM-Signature tag list from a signed message.
     *
     * @return array<string, string>
     */
    private function signatureTags(string $signed): array
    {
        [$headerBlock] = explode("\r\n\r\n", $signed, 2);
        $dkimLine      = explode("\r\n", $headerBlock)[0];
        $value         = trim(substr($dkimLine, strlen('DKIM-Signature:')));

        $tags = [];

        foreach (explode(';', $value) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$key, $val]      = explode('=', $pair, 2);
            $tags[trim($key)] = trim($val);
        }

        return $tags;
    }

    /**
     * @param list<string> $lines
     */
    private function findHeader(array $lines, string $name): string
    {
        foreach ($lines as $line) {
            if (str_starts_with(strtolower($line), strtolower($name . ':'))) {
                return substr($line, strlen($name) + 1);
            }
        }

        return '';
    }

    private function relaxHeader(string $name, string $value): string
    {
        return strtolower(trim($name)) . ':' . $this->relaxValue($value);
    }

    private function relaxValue(string $value): string
    {
        return trim((string) preg_replace('/[ \t]+/', ' ', $value));
    }
}
