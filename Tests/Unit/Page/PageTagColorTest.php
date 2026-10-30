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

namespace TheliaCMS\Tests\Unit\Page;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\PageTagColor;

final class PageTagColorTest extends TestCase
{
    public function testEachColorHasABadgeOfItsOwnThatIsNotTheOneOfNoColor(): void
    {
        $badges = array_map(static fn (PageTagColor $color): string => $color->badgeClass(), PageTagColor::cases());

        self::assertSame($badges, array_unique($badges));
        self::assertNotContains(PageTagColor::NO_COLOR_BADGE, $badges);
        self::assertSame(PageTagColor::NO_COLOR_BADGE, PageTagColor::badgeClassOf(null));
        self::assertSame('text-bg-danger', PageTagColor::badgeClassOf(PageTagColor::Red));
    }

    public function testAStoredNameOutsideThePaletteReadsAsNoColor(): void
    {
        self::assertSame(PageTagColor::Yellow, PageTagColor::fromStored('yellow'));
        self::assertNull(PageTagColor::fromStored(null));
        self::assertNull(PageTagColor::fromStored('pink'));
        self::assertNull(PageTagColor::fromStored('Yellow'), 'The stored name is the lowercase one.');
    }

    public function testEveryLabelIsTranslated(): void
    {
        foreach (['fr_FR', 'en_US'] as $locale) {
            $catalogue = require \dirname(__DIR__, 3).'/I18n/'.$locale.'.php';

            foreach ([...array_map(static fn (PageTagColor $color): string => $color->label(), PageTagColor::cases()), 'No color'] as $label) {
                self::assertArrayHasKey($label, $catalogue, \sprintf('%s has no %s translation.', $label, $locale));
            }
        }
    }
}
