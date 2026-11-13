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

namespace TheliaCMS\Tests\Unit\Content\Site;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Generation\Component\ComponentPrompt;

/**
 * What the bench saw (round 8): asked for related products with a button
 * under them, the generator drew the band of the theme and, beside it, a link
 * of its own (3 trials out of 3), though the band offers a button as an object
 * prop. The prompt says a part the drawn component offers is handed to it.
 */
final class OfferedPartsRuleOfThePromptTest extends TestCase
{
    public function testThePromptSaysAPartTheDrawnComponentOffersIsHandedToIt(): void
    {
        $rule = ComponentPrompt::OFFERED_PARTS_RULE;

        self::assertStringContainsString('its heading, its button', $rule);
        self::assertStringContainsString('never drawn again beside it', $rule);
        self::assertStringContainsString('`button: props.button`', $rule, 'An object prop is handed on whole, as the row the page stores.');
    }
}
