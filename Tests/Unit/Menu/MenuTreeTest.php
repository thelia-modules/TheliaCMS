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

use PHPUnit\Framework\TestCase;
use TheliaCMS\Menu\MenuPlacement;
use TheliaCMS\Menu\MenuTree;

final class MenuTreeTest extends TestCase
{
    private MenuTree $tree;

    protected function setUp(): void
    {
        $this->tree = new MenuTree();
    }

    public function testItReadsChildrenInPositionOrder(): void
    {
        $placements = [
            new MenuPlacement(id: 1, parent: 0, position: 2),
            new MenuPlacement(id: 2, parent: 0, position: 1),
            new MenuPlacement(id: 3, parent: 2, position: 1),
        ];

        self::assertSame([2, 1], $this->tree->children($placements, MenuTree::ROOT));
        self::assertSame([3], $this->tree->children($placements, 2));
        self::assertSame([], $this->tree->children($placements, 1));
    }

    public function testItFlattensDepthFirstWithDepths(): void
    {
        $placements = [
            new MenuPlacement(id: 1, parent: 0, position: 1),
            new MenuPlacement(id: 2, parent: 1, position: 1),
            new MenuPlacement(id: 3, parent: 2, position: 1),
            new MenuPlacement(id: 4, parent: 0, position: 2),
        ];

        self::assertSame([
            ['id' => 1, 'depth' => 1],
            ['id' => 2, 'depth' => 2],
            ['id' => 3, 'depth' => 3],
            ['id' => 4, 'depth' => 1],
        ], $this->tree->flatten($placements));
    }

    /**
     * An entry whose parent has been deleted has to stay reachable, or it would
     * be stored and invisible at the same time.
     */
    public function testItShowsAnOrphanAtTheTopLevel(): void
    {
        $placements = [new MenuPlacement(id: 7, parent: 999, position: 1)];

        self::assertSame([['id' => 7, 'depth' => 1]], $this->tree->flatten($placements));
    }

    public function testItMeasuresTheHeightOfASubtree(): void
    {
        $placements = [
            new MenuPlacement(id: 1, parent: 0, position: 1),
            new MenuPlacement(id: 2, parent: 1, position: 1),
            new MenuPlacement(id: 3, parent: 2, position: 1),
        ];

        self::assertSame(3, $this->tree->heightOf($placements, 1));
        self::assertSame(2, $this->tree->heightOf($placements, 2));
        self::assertSame(1, $this->tree->heightOf($placements, 3));
    }

    public function testItAllowsNestingWhileTheSubtreeStaysWithinThreeLevels(): void
    {
        $placements = [
            new MenuPlacement(id: 1, parent: 0, position: 1),
            new MenuPlacement(id: 2, parent: 1, position: 1),
            new MenuPlacement(id: 3, parent: 0, position: 2),
        ];

        // 3 is a leaf: under 2 it becomes the third level.
        self::assertTrue($this->tree->canNest($placements, 3, 2));
        self::assertTrue($this->tree->canNest($placements, 3, 1));
    }

    public function testItRefusesNestingThatWouldPushASubtreePastThirdLevel(): void
    {
        $placements = [
            new MenuPlacement(id: 1, parent: 0, position: 1),
            new MenuPlacement(id: 2, parent: 1, position: 1),
            new MenuPlacement(id: 3, parent: 0, position: 2),
            new MenuPlacement(id: 4, parent: 3, position: 1),
        ];

        // 3 carries a child, so under 2 it would need a fourth level.
        self::assertFalse($this->tree->canNest($placements, 3, 2));
    }

    public function testItRefusesFilingAnEntryUnderItselfOrItsOwnChild(): void
    {
        $placements = [
            new MenuPlacement(id: 1, parent: 0, position: 1),
            new MenuPlacement(id: 2, parent: 1, position: 1),
        ];

        self::assertFalse($this->tree->canNest($placements, 1, 1));
        self::assertFalse($this->tree->canNest($placements, 1, 2));
    }

    public function testTheTopLevelAlwaysAcceptsAnEntry(): void
    {
        $placements = [
            new MenuPlacement(id: 1, parent: 0, position: 1),
            new MenuPlacement(id: 2, parent: 1, position: 1),
            new MenuPlacement(id: 3, parent: 2, position: 1),
        ];

        self::assertTrue($this->tree->canNest($placements, 1, MenuTree::ROOT));
    }

    public function testItListsDescendantsDepthFirst(): void
    {
        $placements = [
            new MenuPlacement(id: 1, parent: 0, position: 1),
            new MenuPlacement(id: 2, parent: 1, position: 1),
            new MenuPlacement(id: 3, parent: 2, position: 1),
            new MenuPlacement(id: 4, parent: 1, position: 2),
        ];

        self::assertSame([2, 3, 4], $this->tree->descendants($placements, 1));
        self::assertSame([], $this->tree->descendants($placements, 3));
    }

    /**
     * Rows can only ever point at each other in a loop through corrupted data,
     * and a tree walk that hangs takes the whole back office with it.
     */
    public function testItSurvivesACycle(): void
    {
        $placements = [
            new MenuPlacement(id: 1, parent: 2, position: 1),
            new MenuPlacement(id: 2, parent: 1, position: 1),
        ];

        self::assertSame([], $this->tree->flatten($placements));
    }
}
