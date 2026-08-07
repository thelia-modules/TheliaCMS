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

namespace TheliaCMS\Tests\Unit\Script;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Script\ScriptPlacement;

final class ScriptPlacementTest extends TestCase
{
    /**
     * The head snippets go to `layout.head.bottom`, never to `layout.head.top`:
     * that is where the consent defaults are emitted, and a tag written above
     * them starts before being told what it may store.
     */
    public function testHeadSnippetsRenderBelowTheConsentLayer(): void
    {
        self::assertSame('layout.head.bottom', ScriptPlacement::Head->hook());
        self::assertNotSame('layout.head.top', ScriptPlacement::Head->hook());
    }

    public function testEachPlacementRendersAtADistinctHookPoint(): void
    {
        $hooks = array_map(
            static fn (ScriptPlacement $placement): string => $placement->hook(),
            ScriptPlacement::cases(),
        );

        self::assertSame($hooks, array_unique($hooks));
    }

    /**
     * A placement read back from a row written by an older version, or by hand,
     * must not take down the page it is rendered into.
     */
    public function testAnUnknownPlacementFallsBackToTheHead(): void
    {
        self::assertSame(ScriptPlacement::Head, ScriptPlacement::fromStorage('footer'));
        self::assertSame(ScriptPlacement::Head, ScriptPlacement::fromStorage(null));
        self::assertSame(ScriptPlacement::Head, ScriptPlacement::fromStorage(''));
    }

    public function testEveryPlacementIsOfferedAsAChoice(): void
    {
        self::assertSame(
            array_map(static fn (ScriptPlacement $case): string => $case->value, ScriptPlacement::cases()),
            array_values(ScriptPlacement::choices()),
        );
    }
}
