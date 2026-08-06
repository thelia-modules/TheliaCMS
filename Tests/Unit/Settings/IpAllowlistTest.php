<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TheliaCMS\Tests\Unit\Settings;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Settings\IpAllowlist;

final class IpAllowlistTest extends TestCase
{
    private IpAllowlist $allowlist;

    protected function setUp(): void
    {
        $this->allowlist = new IpAllowlist();
    }

    #[DataProvider('validEntries')]
    public function testItAcceptsAnAddressOrARange(string $entry): void
    {
        self::assertTrue($this->allowlist->isValidEntry($entry));
    }

    public static function validEntries(): iterable
    {
        yield 'v4' => ['203.0.113.42'];
        yield 'v4 range' => ['203.0.113.0/24'];
        yield 'v4 single host range' => ['203.0.113.42/32'];
        yield 'v6' => ['2001:db8::1'];
        yield 'v6 range' => ['2001:db8::/32'];
        yield 'loopback' => ['127.0.0.1'];
        yield 'surrounding spaces' => ['  203.0.113.42  '];
    }

    #[DataProvider('invalidEntries')]
    public function testItRefusesAnythingElse(string $entry): void
    {
        self::assertFalse($this->allowlist->isValidEntry($entry));
    }

    public static function invalidEntries(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'host name' => ['example.org'];
        yield 'typo' => ['203.0.113'];
        yield 'out of range octet' => ['203.0.113.999'];
        yield 'mask beyond v4' => ['203.0.113.0/33'];
        yield 'mask beyond v6' => ['2001:db8::/129'];
        yield 'empty mask' => ['203.0.113.0/'];
        yield 'non numeric mask' => ['203.0.113.0/x'];
    }

    public function testItNamesEveryEntryItRefuses(): void
    {
        self::assertSame(
            ['nope', '203.0.113.999'],
            $this->allowlist->rejected(['203.0.113.42', 'nope', '2001:db8::/32', '203.0.113.999']),
        );
    }

    public function testItMatchesAnAddressAgainstTheList(): void
    {
        $entries = ['203.0.113.42', '198.51.100.0/24', '2001:db8::/32'];

        self::assertTrue($this->allowlist->contains($entries, '203.0.113.42'));
        self::assertTrue($this->allowlist->contains($entries, '198.51.100.7'));
        self::assertTrue($this->allowlist->contains($entries, '2001:db8::dead'));
        self::assertFalse($this->allowlist->contains($entries, '203.0.113.43'));
    }

    /**
     * A request whose client address cannot be determined must not be taken for
     * one on the list: the site stays closed.
     */
    public function testAnUnknownAddressMatchesNothing(): void
    {
        self::assertFalse($this->allowlist->contains(['203.0.113.42'], null));
        self::assertFalse($this->allowlist->contains(['203.0.113.42'], ''));
    }

    public function testAnEmptyListLetsNobodyThrough(): void
    {
        self::assertFalse($this->allowlist->contains([], '203.0.113.42'));
    }
}
