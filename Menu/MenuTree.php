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

namespace TheliaCMS\Menu;

/**
 * Tree arithmetic of a menu: who sits under whom, and how deep.
 *
 * Kept apart from storage on purpose — the rules that decide whether an entry
 * may be indented are the ones most likely to be wrong, and here they are
 * decided on plain integers.
 */
final readonly class MenuTree
{
    /**
     * A menu is navigation, not a sitemap: three levels are already more than
     * a visitor can hold, and the depth every theme has to style is bounded.
     */
    public const int MAX_DEPTH = 3;

    /** Entries whose parent is absent from the set are treated as roots. */
    public const int ROOT = 0;

    /**
     * Direct children of `$parent`, in display order.
     *
     * @param list<MenuPlacement> $placements
     *
     * @return list<int>
     */
    public function children(array $placements, int $parent): array
    {
        $children = array_filter(
            $placements,
            fn (MenuPlacement $placement): bool => $this->parentOf($placements, $placement) === $parent,
        );

        usort($children, static fn (MenuPlacement $a, MenuPlacement $b): int => [$a->position, $a->id] <=> [$b->position, $b->id]);

        return array_map(static fn (MenuPlacement $placement): int => $placement->id, array_values($children));
    }

    /**
     * The whole menu depth-first, each entry with its depth (roots at 1), which
     * is exactly what a back-office list needs to indent rows.
     *
     * @param list<MenuPlacement> $placements
     *
     * @return list<array{id: int, depth: int}>
     */
    public function flatten(array $placements, int $parent = self::ROOT, int $depth = 1): array
    {
        $flat = [];

        foreach ($this->children($placements, $parent) as $id) {
            $flat[] = ['id' => $id, 'depth' => $depth];

            // Cannot recurse for ever: a child is always deeper than its
            // parent, and the depth of the set is bounded by its size.
            if ($depth <= \count($placements)) {
                $flat = array_merge($flat, $this->flatten($placements, $id, $depth + 1));
            }
        }

        return $flat;
    }

    /**
     * How many levels the entry occupies, itself included: 1 for a leaf.
     *
     * @param list<MenuPlacement> $placements
     */
    public function heightOf(array $placements, int $id): int
    {
        $height = 1;

        foreach ($this->children($placements, $id) as $childId) {
            $height = max($height, 1 + $this->heightOf($placements, $childId));
        }

        return $height;
    }

    /**
     * @param list<MenuPlacement> $placements
     */
    public function depthOf(array $placements, int $id): int
    {
        foreach ($this->flatten($placements) as $row) {
            if ($row['id'] === $id) {
                return $row['depth'];
            }
        }

        return 1;
    }

    /**
     * Whether the entry may become a child of `$parent` without pushing part of
     * its own subtree past the maximum depth.
     *
     * @param list<MenuPlacement> $placements
     */
    public function canNest(array $placements, int $id, int $parent): bool
    {
        if (self::ROOT === $parent) {
            return true;
        }

        if ($id === $parent || \in_array($parent, $this->descendants($placements, $id), true)) {
            return false;
        }

        return $this->depthOf($placements, $parent) + $this->heightOf($placements, $id) <= self::MAX_DEPTH;
    }

    /**
     * @param list<MenuPlacement> $placements
     *
     * @return list<int>
     */
    public function descendants(array $placements, int $id): array
    {
        $descendants = [];

        foreach ($this->children($placements, $id) as $childId) {
            $descendants[] = $childId;
            $descendants = array_merge($descendants, $this->descendants($placements, $childId));
        }

        return $descendants;
    }

    /**
     * The parents of a copied menu, in the ids of the copy.
     *
     * `parent` stores the id of another entry, so a copy whose entries got
     * new ids has to point them at each other again. An entry whose parent is
     * not in the set is shown at the root, and is copied there.
     *
     * @param list<MenuPlacement> $placements of the original entries
     * @param array<int, int>     $newIds     id of the copy, keyed by the id of the original
     *
     * @return array<int, int> parent in the copy, keyed by the id of the copy
     */
    public function remappedParents(array $placements, array $newIds): array
    {
        $parents = [];

        foreach ($placements as $placement) {
            $copy = $newIds[$placement->id] ?? null;

            if (null === $copy) {
                continue;
            }

            $parents[$copy] = $newIds[$this->parentOf($placements, $placement)] ?? self::ROOT;
        }

        return $parents;
    }

    /**
     * The parent an entry is actually shown under: a row pointing at an entry
     * that is not in the menu any more would otherwise disappear from the tree
     * while still being stored.
     *
     * @param list<MenuPlacement> $placements
     */
    private function parentOf(array $placements, MenuPlacement $placement): int
    {
        if (self::ROOT === $placement->parent) {
            return self::ROOT;
        }

        foreach ($placements as $candidate) {
            if ($candidate->id === $placement->parent) {
                return $placement->parent;
            }
        }

        return self::ROOT;
    }
}
