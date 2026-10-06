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
use TheliaCMS\Settings\DisabledBlocks;

final class DisabledBlocksTest extends TestCase
{
    public function testNothingIsOffUntilSomethingIsStored(): void
    {
        foreach ([null, '', ' , '] as $raw) {
            $blocks = DisabledBlocks::fromStorage($raw);

            self::assertTrue($blocks->isEmpty());
            self::assertFalse($blocks->contains('cms-hero'));
            self::assertSame('', $blocks->toStorage());
        }
    }

    public function testItReadsAndWritesTheStoredList(): void
    {
        $blocks = DisabledBlocks::fromStorage(' cms-logos, cms-menu ,,cms-logos');

        self::assertSame(['cms-logos', 'cms-menu'], $blocks->ids(), 'Blanks and duplicates are not blocks.');
        self::assertTrue($blocks->contains('cms-menu'));
        self::assertFalse($blocks->contains('cms-hero'));
        self::assertSame('cms-logos,cms-menu', $blocks->toStorage());
        self::assertSame($blocks->ids(), DisabledBlocks::fromStorage($blocks->toStorage())->ids(), 'What is written is read back unchanged.');
    }

    /**
     * The list may come from an import document, where anything can sit.
     */
    public function testOnlyNamesAreKept(): void
    {
        $blocks = new DisabledBlocks(['cms-logos', 3, null, '', ['cms-menu'], ' cms-gallery ']);

        self::assertSame(['cms-logos', 'cms-gallery'], $blocks->ids());
    }

    public function testTheScreenSwitchesOffWhatWasLeftUnticked(): void
    {
        $before = DisabledBlocks::fromStorage('cms-logos,other-module-block');

        $after = $before->afterChoosing(
            offered: ['cms-hero', 'cms-logos', 'cms-menu'],
            enabled: ['cms-hero', 'cms-logos'],
        );

        self::assertFalse($after->contains('cms-hero'), 'Ticked: offered.');
        self::assertFalse($after->contains('cms-logos'), 'Ticked again: back in the panel.');
        self::assertTrue($after->contains('cms-menu'), 'Unticked: off.');
        self::assertTrue($after->contains('other-module-block'), 'Not on the screen, so not a choice: kept as it was.');
        self::assertSame($before->ids(), DisabledBlocks::fromStorage('cms-logos,other-module-block')->ids(), 'The value is immutable.');
    }

    public function testTickingEverythingClearsTheOffer(): void
    {
        $after = DisabledBlocks::fromStorage('cms-logos')->afterChoosing(['cms-logos', 'cms-hero'], ['cms-hero', 'cms-logos']);

        self::assertTrue($after->isEmpty());
    }
}
