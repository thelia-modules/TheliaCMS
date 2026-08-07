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
use Propel\Runtime\Propel;
use TheliaCMS\Http\CachePurger;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\Menu\MenuCache;
use TheliaCMS\Menu\MenuTargetType;
use TheliaCMS\Menu\MenuTree;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Page\Admin\CmsActivityLog;
use TheliaCMS\Security\CmsResources;

/**
 * Every write to a menu goes through here, so no caller can forget the two
 * things that must travel with a change: the cached menus have to be dropped,
 * and the change has to end up in the activity log.
 */
final readonly class CmsMenuWriter
{
    public function __construct(
        private CmsMenuRepository $menus,
        private MenuTree $tree,
        private MenuCache $cache,
        // Menus are drawn on every page, so a change to one makes every
        // cached page stale.
        private CachePurger $httpCache,
        private CmsActivityLog $activityLog,
    ) {
    }

    /**
     * @throws \DomainException when the code is already taken by another menu
     */
    public function saveMenu(CmsMenu $menu, string $locale, string $code, string $title): void
    {
        $existing = CmsMenuQuery::create()->findOneByCode($code);

        if (null !== $existing && $existing->getId() !== $menu->getId()) {
            throw new \DomainException('Another menu already uses this code.');
        }

        $wasNew = $menu->isNew();

        $menu->setCode($code);
        $menu->setLocale($locale)->setTitle($title);
        $menu->save();

        $this->cache->invalidate();
        $this->httpCache->purge([CacheTags::MENUS]);
        $this->log($wasNew ? 'CREATE' : 'UPDATE', $menu, \sprintf('CMS menu "%s" saved', $code));
    }

    public function deleteMenu(CmsMenu $menu): void
    {
        $code = (string) $menu->getCode();
        $id = (int) $menu->getId();

        // Entries go with it through the database cascade: they have no meaning
        // outside their menu, and nothing else points at them.
        $menu->delete();

        $this->cache->invalidate();
        $this->httpCache->purge([CacheTags::MENUS]);
        $this->activityLog->record('DELETE', $id, \sprintf('CMS menu "%s" deleted', $code), CmsResources::MENU);
    }

    /**
     * @throws \DomainException when the entry cannot sit where it is asked to
     */
    public function saveItem(CmsMenu $menu, ?CmsMenuItem $item, string $locale, MenuItemData $data): CmsMenuItem
    {
        $placements = $this->menus->placements((int) $menu->getId());
        $isNew = null === $item;
        $parent = $data->parent;

        if (MenuTree::ROOT !== $parent) {
            $fits = $isNew
                ? $this->tree->depthOf($placements, $parent) < MenuTree::MAX_DEPTH
                : $this->tree->canNest($placements, (int) $item->getId(), $parent);

            if (!$fits) {
                throw new \DomainException('A menu is three levels deep at most, and an entry cannot be filed under itself.');
            }
        }

        $item ??= (new CmsMenuItem())->setMenuId($menu->getId());

        $moved = (int) $item->getParent() !== $parent;

        $item->setParent($parent)
            ->setTargetType($data->type->value)
            ->setTargetId($data->type->needsTargetId() ? $data->targetId : null)
            ->setUrl(MenuTargetType::Url === $data->type ? $data->url : null)
            ->setOpenNewTab($data->openNewTab ? 1 : 0);

        if ($isNew || $moved) {
            $item->setPosition($this->nextPosition((int) $menu->getId(), $parent));
        }

        $item->setLocale($locale)->setLabel($data->label);
        $item->save();

        $this->cache->invalidate();
        $this->httpCache->purge([CacheTags::MENUS]);
        $this->log($isNew ? 'CREATE' : 'UPDATE', $menu, \sprintf('CMS menu "%s": entry #%d saved', $menu->getCode(), $item->getId()));

        return $item;
    }

    /**
     * Removes the entry and everything under it.
     *
     * The cascade is deliberate: menu entries have no bin, and leaving orphans
     * behind would make them reappear at the top level of the menu — visible on
     * the site, in a place nobody put them.
     */
    public function deleteItem(CmsMenu $menu, CmsMenuItem $item): void
    {
        $placements = $this->menus->placements((int) $menu->getId());
        $ids = [(int) $item->getId(), ...$this->tree->descendants($placements, (int) $item->getId())];

        $connection = Propel::getConnection('TheliaMain');
        $connection->beginTransaction();

        try {
            CmsMenuItemQuery::create()
                ->filterByMenuId($menu->getId())
                ->filterById($ids, Criteria::IN)
                ->delete($connection);

            $connection->commit();
        } catch (\Throwable $throwable) {
            $connection->rollBack();

            throw $throwable;
        }

        $this->cache->invalidate();
        $this->httpCache->purge([CacheTags::MENUS]);
        $this->log('DELETE', $menu, \sprintf('CMS menu "%s": entries removed (%s)', $menu->getCode(), implode(', ', $ids)));
    }

    /**
     * Moves the entry one slot up or down among the entries it shares a parent
     * with.
     */
    public function move(CmsMenu $menu, CmsMenuItem $item, int $direction): void
    {
        $placements = $this->menus->placements((int) $menu->getId());
        $siblings = $this->tree->children($placements, (int) $item->getParent());
        $offset = array_search((int) $item->getId(), $siblings, true);

        if (!\is_int($offset)) {
            return;
        }

        $target = $offset + ($direction < 0 ? -1 : 1);

        if ($target < 0 || $target >= \count($siblings)) {
            return;
        }

        [$siblings[$offset], $siblings[$target]] = [$siblings[$target], $siblings[$offset]];

        $this->reorder((int) $menu->getId(), $siblings);

        $this->cache->invalidate();
        $this->httpCache->purge([CacheTags::MENUS]);
        $this->log('UPDATE', $menu, \sprintf('CMS menu "%s": entry #%d moved', $menu->getCode(), $item->getId()));
    }

    /**
     * Files the entry under `$parent` at `$position` — what dragging a row in
     * the editor amounts to.
     *
     * @throws \DomainException when the entry cannot sit there
     */
    public function place(CmsMenu $menu, CmsMenuItem $item, int $parent, int $position): void
    {
        $placements = $this->menus->placements((int) $menu->getId());
        $id = (int) $item->getId();

        if (!$this->tree->canNest($placements, $id, $parent)) {
            throw new \DomainException('A menu is three levels deep at most, and an entry cannot be filed under itself.');
        }

        $item->setParent($parent)->save();

        // Recomputed from the tree *after* the move, so the entry is inserted
        // among its new siblings rather than appended.
        $siblings = $this->tree->children($this->menus->placements((int) $menu->getId()), $parent);
        $siblings = array_values(array_filter($siblings, static fn (int $sibling): bool => $sibling !== $id));
        array_splice($siblings, max(0, min($position - 1, \count($siblings))), 0, [$id]);

        $this->reorder((int) $menu->getId(), $siblings);

        $this->cache->invalidate();
        $this->httpCache->purge([CacheTags::MENUS]);
        $this->log('UPDATE', $menu, \sprintf('CMS menu "%s": entry #%d moved', $menu->getCode(), $id));
    }

    /**
     * @param list<int> $ids
     */
    private function reorder(int $menuId, array $ids): void
    {
        $items = CmsMenuItemQuery::create()
            ->filterByMenuId($menuId)
            ->filterById($ids, Criteria::IN)
            ->find();

        foreach ($items as $item) {
            $position = array_search((int) $item->getId(), $ids, true);

            if (\is_int($position)) {
                $item->setPosition($position + 1)->save();
            }
        }
    }

    private function nextPosition(int $menuId, int $parent): int
    {
        $last = CmsMenuItemQuery::create()
            ->filterByMenuId($menuId)
            ->filterByParent($parent)
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        return null === $last ? 1 : (int) $last->getPosition() + 1;
    }

    private function log(string $action, CmsMenu $menu, string $message): void
    {
        $this->activityLog->record($action, (int) $menu->getId(), $message, CmsResources::MENU);
    }
}
