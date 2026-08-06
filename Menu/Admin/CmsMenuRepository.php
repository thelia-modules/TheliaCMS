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

namespace TheliaCMS\Menu\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Menu\MenuPlacement;
use TheliaCMS\Menu\MenuTargetResolver;
use TheliaCMS\Menu\MenuTargetType;
use TheliaCMS\Menu\MenuTree;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;

/**
 * Reads menus for the back office.
 *
 * Unlike the front-office provider, this one keeps the entries whose target is
 * broken and says what is wrong with them: they are invisible to visitors, so
 * the menu screen is the only place they can be noticed and fixed.
 */
final readonly class CmsMenuRepository
{
    public function __construct(
        private MenuTargetResolver $targets,
        private MenuTree $tree,
    ) {
    }

    /**
     * @return list<array{menu: CmsMenu, entries: int, issues: int}>
     */
    public function menus(string $locale): array
    {
        $menus = CmsMenuQuery::create()
            ->joinWithI18n($locale, Criteria::LEFT_JOIN)
            ->orderByCode()
            ->find();

        $summaries = [];

        foreach ($menus as $menu) {
            $rows = $this->rows($menu, $locale);

            $summaries[] = [
                'menu' => $menu,
                'entries' => \count($rows),
                'issues' => \count(array_filter($rows, static fn (MenuItemRow $row): bool => !$row->target->isUsable())),
            ];
        }

        return $summaries;
    }

    public function find(int $id): ?CmsMenu
    {
        return CmsMenuQuery::create()->findPk($id);
    }

    public function findByCode(string $code): ?CmsMenu
    {
        return CmsMenuQuery::create()->findOneByCode($code);
    }

    /**
     * The whole menu, depth-first, ready to be indented.
     *
     * @return list<MenuItemRow>
     */
    public function rows(CmsMenu $menu, string $locale): array
    {
        $items = $this->items((int) $menu->getId());
        $placements = $this->placementsOf($items);
        $rows = [];

        foreach ($this->tree->flatten($placements) as $flat) {
            $item = $items[$flat['id']] ?? null;

            if (null === $item) {
                continue;
            }

            $siblings = $this->tree->children($placements, $this->shownParent($placements, $flat['id']));
            $offset = array_search($flat['id'], $siblings, true);

            $rows[] = new MenuItemRow(
                item: $item,
                depth: $flat['depth'],
                type: MenuTargetType::fromStorage($item->getTargetType()),
                target: $this->targets->resolve($item, $locale),
                canMoveUp: \is_int($offset) && $offset > 0,
                canMoveDown: \is_int($offset) && $offset < \count($siblings) - 1,
            );
        }

        return $rows;
    }

    public function findItem(CmsMenu $menu, int $itemId): ?CmsMenuItem
    {
        return CmsMenuItemQuery::create()
            ->filterByMenuId($menu->getId())
            ->filterById($itemId)
            ->findOne();
    }

    /**
     * Entries this one may be filed under: not itself, not its own descendants,
     * and nothing that would push its subtree past the third level.
     *
     * @return array<string, int>
     */
    public function parentChoices(CmsMenu $menu, string $locale, ?int $itemId = null): array
    {
        $items = $this->items((int) $menu->getId());
        $placements = $this->placementsOf($items);
        $choices = [];

        foreach ($this->tree->flatten($placements) as $flat) {
            $item = $items[$flat['id']] ?? null;

            if (null === $item) {
                continue;
            }

            $allowed = null === $itemId
                ? $flat['depth'] < MenuTree::MAX_DEPTH
                : $this->tree->canNest($placements, $itemId, $flat['id']);

            if (!$allowed) {
                continue;
            }

            $label = $this->targets->resolve($item, $locale)->label;
            $choices[str_repeat('— ', $flat['depth'] - 1).('' === $label ? '#'.$flat['id'] : $label)] = $flat['id'];
        }

        return $choices;
    }

    /**
     * @return list<MenuPlacement>
     */
    public function placements(int $menuId): array
    {
        return $this->placementsOf($this->items($menuId));
    }

    /**
     * @return array<int, CmsMenuItem>
     */
    private function items(int $menuId): array
    {
        $items = [];

        foreach (CmsMenuItemQuery::create()->filterByMenuId($menuId)->orderByPosition()->find() as $item) {
            $items[(int) $item->getId()] = $item;
        }

        return $items;
    }

    /**
     * @param array<int, CmsMenuItem> $items
     *
     * @return list<MenuPlacement>
     */
    private function placementsOf(array $items): array
    {
        return array_values(array_map(
            static fn (CmsMenuItem $item): MenuPlacement => new MenuPlacement(
                (int) $item->getId(),
                (int) $item->getParent(),
                (int) $item->getPosition(),
            ),
            $items
        ));
    }

    /**
     * @param list<MenuPlacement> $placements
     */
    private function shownParent(array $placements, int $id): int
    {
        foreach ($placements as $placement) {
            if ($placement->id !== $id) {
                continue;
            }

            foreach ($placements as $candidate) {
                if ($candidate->id === $placement->parent) {
                    return $placement->parent;
                }
            }

            return MenuTree::ROOT;
        }

        return MenuTree::ROOT;
    }
}
