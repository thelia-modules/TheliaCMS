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

namespace TheliaCMS\Tests\Unit\Generation;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Generation\Brief;

/**
 * The web pages a brief cites, which the model may read before it writes.
 */
final class BriefTest extends TestCase
{
    public function testTheAddressesABriefCitesAreFoundWithoutThePunctuationOfTheSentence(): void
    {
        $brief = new Brief('Reprends https://example.com/a-propos. Le ton de (http://example.org/blog?page=2), et https://example.com/a-propos encore !');

        self::assertSame(['https://example.com/a-propos', 'http://example.org/blog?page=2'], $brief->addresses());
    }

    public function testABriefWithoutAnAddressCitesNone(): void
    {
        self::assertSame([], (new Brief('Une page sur nos services, voir www.example.com plus tard.'))->addresses());
    }
}
