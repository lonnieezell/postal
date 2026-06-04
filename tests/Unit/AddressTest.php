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
}
