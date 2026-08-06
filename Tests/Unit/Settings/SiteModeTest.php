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

use PHPUnit\Framework\TestCase;
use TheliaCMS\Settings\SiteMode;

final class SiteModeTest extends TestCase
{
    public function testItReadsTheStoredValue(): void
    {
        self::assertSame(SiteMode::Showcase, SiteMode::fromStorage('vitrine'));
        self::assertSame(SiteMode::Commerce, SiteMode::fromStorage('commerce'));
    }

    /**
     * Anything unreadable has to mean "shop": a site that closes its cart
     * because a setting was mistyped loses orders.
     */
    public function testAnythingElseIsAShop(): void
    {
        self::assertSame(SiteMode::Commerce, SiteMode::fromStorage(null));
        self::assertSame(SiteMode::Commerce, SiteMode::fromStorage(''));
        self::assertSame(SiteMode::Commerce, SiteMode::fromStorage('showcase'));
        self::assertSame(SiteMode::Commerce, SiteMode::fromStorage('VITRINE'));
    }

    public function testOnlyTheShowcaseModeIsAShowcase(): void
    {
        self::assertTrue(SiteMode::Showcase->isShowcase());
        self::assertFalse(SiteMode::Commerce->isShowcase());
    }
}
