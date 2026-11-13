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

namespace TheliaCMS\Tests\Unit\Front;

use PHPUnit\Framework\TestCase;

/**
 * Flexy gives each heading a size for a phone (`--font-size-h2-m`) and one
 * for a wider screen (`--font-size-h2-d`). The blocks read the wide one
 * everywhere: on a phone a heading of a section (36px) stood larger than the
 * title of the page (28px). Every heading of the blocks reads the size of
 * its level, which takes the phone size below 48rem.
 */
final class HeadingScaleTest extends TestCase
{
    public function testEveryHeadingOfTheBlocksReadsTheSizeOfItsLevelForTheScreen(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 3).'/assets/blocks.css');

        preg_match_all('/font-size:\s*var\(--font-size-h\d-[dm]\b/', $css, $direct);
        self::assertSame([], $direct[0], 'No heading reads the size of the theme for one screen only.');
        self::assertGreaterThanOrEqual(11, preg_match_all('/font-size:\s*var\(--cms-font-size-h[1-3]\)/', $css));

        foreach ([1, 2, 3] as $level) {
            self::assertStringContainsString(\sprintf('--cms-font-size-h%d: var(--font-size-h%1$d-m, var(--font-size-h%1$d-d,', $level), $css, 'On a phone, the phone size of the theme.');
            self::assertMatchesRegularExpression(\sprintf('/@media \(min-width: 48rem\) \{\s*:root \{[^}]*--cms-font-size-h%d: var\(--font-size-h%1$d-d,/', $level), $css, 'From 48rem, the wide size.');
        }
    }
}
