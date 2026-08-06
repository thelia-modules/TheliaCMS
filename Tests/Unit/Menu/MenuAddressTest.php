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

namespace TheliaCMS\Tests\Unit\Menu;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Menu\MenuAddress;

final class MenuAddressTest extends TestCase
{
    private MenuAddress $addresses;

    protected function setUp(): void
    {
        $this->addresses = new MenuAddress();
    }

    #[DataProvider('allowedAddresses')]
    public function testItKeepsAnAddressAMenuMayPointAt(string $typed, string $expected): void
    {
        self::assertSame($expected, $this->addresses->normalize($typed));
    }

    public static function allowedAddresses(): iterable
    {
        yield 'path of the site' => ['/contact', '/contact'];
        yield 'anchor' => ['#prices', '#prices'];
        yield 'https' => ['https://example.org/a', 'https://example.org/a'];
        yield 'http' => ['http://example.org', 'http://example.org'];
        yield 'mail' => ['mailto:hello@example.org', 'mailto:hello@example.org'];
        yield 'phone' => ['tel:+33123456789', 'tel:+33123456789'];
        yield 'surrounding spaces' => ['  /contact  ', '/contact'];

        // Typed the way it is read out loud: completed rather than refused.
        yield 'bare domain' => ['example.org', 'https://example.org'];
        yield 'bare domain with a path' => ['example.org/prices', 'https://example.org/prices'];
        yield 'bare domain with a fragment' => ['example.org/a#b', 'https://example.org/a#b'];
        yield 'bare domain with a port' => ['example.org:8443', 'https://example.org:8443'];
    }

    #[DataProvider('refusedAddresses')]
    public function testItRefusesAnAddressThatHasNoBusinessInALink(string $typed): void
    {
        self::assertNull($this->addresses->normalize($typed));
    }

    public static function refusedAddresses(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'script' => ['javascript:alert(1)'];
        yield 'script, spelled out' => ['JavaScript:alert(1)'];
        yield 'inline document' => ['data:text/html,<script>alert(1)</script>'];
        yield 'view source' => ['view-source:https://example.org'];
        yield 'file' => ['file:///etc/passwd'];

        // Two slashes are another host, not a path of this site.
        yield 'protocol relative' => ['//example.org/a'];

        // No dot: a word, most likely a typo, not a host.
        yield 'single word' => ['contact'];
        yield 'trailing dash in a label' => ['example-.org'];
    }

    public function testItAcceptsNull(): void
    {
        self::assertNull($this->addresses->normalize(null));
    }
}
