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

/**
 * @internal
 */
final class AddressTest extends CIUnitTestCase
{
    public function testConstructExposesEmailAndName(): void
    {
        $address = new Address('alice@example.com', 'Alice');

        $this->assertSame('alice@example.com', $address->email);
        $this->assertSame('Alice', $address->name);
    }

    public function testNameDefaultsToEmptyString(): void
    {
        $address = new Address('alice@example.com');

        $this->assertSame('', $address->name);
    }

    public function testFromStringParsesNameAndEmail(): void
    {
        $address = Address::fromString('Alice <alice@example.com>');

        $this->assertSame('alice@example.com', $address->email);
        $this->assertSame('Alice', $address->name);
    }

    public function testFromStringParsesBareEmail(): void
    {
        $address = Address::fromString('alice@example.com');

        $this->assertSame('alice@example.com', $address->email);
        $this->assertSame('', $address->name);
    }

    public function testToStringRendersNameAndEmail(): void
    {
        $address = new Address('alice@example.com', 'Alice');

        $this->assertSame('Alice <alice@example.com>', $address->toString());
    }

    public function testToStringRendersBareEmailWhenNoName(): void
    {
        $address = new Address('alice@example.com');

        $this->assertSame('alice@example.com', $address->toString());
    }

    public function testFromStringTrimsQuotesButKeepsCommaInName(): void
    {
        $address = Address::fromString('"Doe, John" <john@example.com>');

        $this->assertSame('john@example.com', $address->email);
        $this->assertSame('Doe, John', $address->name);
    }

    public function testFromStringTrimsSingleQuotedName(): void
    {
        $address = Address::fromString("'Alice' <alice@example.com>");

        $this->assertSame('alice@example.com', $address->email);
        $this->assertSame('Alice', $address->name);
    }

    public function testFromStringParsesAngleOnlyAddress(): void
    {
        $address = Address::fromString('<only@example.com>');

        $this->assertSame('only@example.com', $address->email);
        $this->assertSame('', $address->name);
    }

    public function testFromStringTrimsWhitespaceAroundNameAndAddress(): void
    {
        $address = Address::fromString('  Bob   <  bob@example.com  >  ');

        $this->assertSame('bob@example.com', $address->email);
        $this->assertSame('Bob', $address->name);
    }

    public function testFromStringWithBracketInsideNameYieldsInvalidAddrSpec(): void
    {
        // An angle bracket inside the display name defeats the loose parser, so
        // the captured addr-spec is malformed. This is safe: the address is
        // rejected by Email's entry validation rather than silently mis-sent.
        $address = Address::fromString('"Weird <x>" <real@example.com>');

        $this->assertFalse(Address::isValid($address->email));
    }

    public function testIsValidAcceptsWellFormedAddress(): void
    {
        $this->assertTrue(Address::isValid('alice@example.com'));
    }

    public function testIsValidRejectsMalformedAndEmptyAddresses(): void
    {
        $this->assertFalse(Address::isValid('not-an-email'));
        $this->assertFalse(Address::isValid(''));
    }
}
