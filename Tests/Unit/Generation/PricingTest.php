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
use TheliaCMS\Generation\Pricing;
use TheliaCMS\Generation\Usage;

final class PricingTest extends TestCase
{
    public function testACallIsPricedFromItsTokens(): void
    {
        $pricing = new Pricing();

        // 10 000 input at $4, 2 000 output at $20, 50 000 cache reads at $0.20, 20 000 cache writes at $5, per million.
        self::assertSame(0.19, $pricing->cost('claude-opus-5-5', new Usage(10000, 2000, 50000, 20000)));
        self::assertSame(0.0, $pricing->cost('claude-opus-5-5', new Usage()));
    }

    public function testAModelTheTableDoesNotKnowIsCountedAtTheHighestPrice(): void
    {
        $pricing = new Pricing();

        self::assertSame($pricing->cost('claude-fable-5-1', new Usage(1000, 1000)), $pricing->cost('claude-something-new', new Usage(1000, 1000)));
    }
}
