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
use TheliaCMS\Menu\SiteRoot;

final class SiteRootTest extends TestCase
{
    #[DataProvider('addresses')]
    public function testItKeepsTheSiteAndDropsThePath(string $url, string $root): void
    {
        self::assertSame($root, SiteRoot::of($url));
    }

    public static function addresses(): iterable
    {
        yield 'page of the site' => ['https://www.example.org/home', 'https://www.example.org/'];
        yield 'port kept' => ['https://shop.test:8443/home?x=1', 'https://shop.test:8443/'];
        yield 'relative address' => ['/home', '/'];
    }
}
