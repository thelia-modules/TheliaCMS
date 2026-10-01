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

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Lang;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;

/**
 * The menu a theme renders: a tree of labels and addresses, nothing else.
 *
 * Entries whose target is gone, offline or unpublished in this locale are left
 * out — a visitor must never be offered a link that answers 404. An entry that
 * cannot be linked but still has children and a label survives as a heading,
 * because dropping it would quietly reshape the menu around it.
 */
final readonly class CmsMenuProvider
{
    public function __construct(
        private MenuCache $cache,
        private MenuTargetResolver $targets,
        private MenuTree $tree,
        private LangService $langService,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<array{id: int, label: string, url: string|null, blank: bool, source: string, source_id: int|null, children: list<array<string, mixed>>, active: bool, in_trail: bool}>
     */
    public function menu(string $code, ?string $locale = null): array
    {
        $locale ??= $this->currentLocale();
        $request = $this->requestStack->getMainRequest();

        $nodes = $this->cache->get(
            $code,
            $locale,
            $request?->getHost() ?? 'cli',
            fn (): array => $this->build($code, $locale),
        );

        // Left out of the cache on purpose: it depends on the page being served,
        // not on the menu.
        return $this->markCurrent($nodes, $request?->getPathInfo() ?? '', $request?->query->all() ?? [], $request?->getHost());
    }

    /**
     * Whether the menu stores at least one entry, whether or not any of them
     * resolves in the language being read.
     *
     * A menu somebody filled in is a merchant taking that part of the site
     * over, even on the day all of its entries are offline: falling back to
     * something else then would show links nobody chose.
     */
    public function hasEntries(string $code): bool
    {
        return $this->cache->storedEntryCount($code, static function () use ($code): int {
            $menu = CmsMenuQuery::create()->findOneByCode($code);

            return null === $menu ? 0 : CmsMenuItemQuery::create()->filterByMenuId($menu->getId())->count();
        }) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function build(string $code, string $locale): array
    {
        $menu = CmsMenuQuery::create()->findOneByCode($code);

        if (null === $menu) {
            return [];
        }

        $items = iterator_to_array(
            CmsMenuItemQuery::create()->filterByMenuId($menu->getId())->orderByPosition()->find(),
            false
        );

        /** @var array<int, CmsMenuItem> $byId */
        $byId = [];
        $placements = [];

        foreach ($items as $item) {
            $byId[(int) $item->getId()] = $item;
            $placements[] = new MenuPlacement((int) $item->getId(), (int) $item->getParent(), (int) $item->getPosition());
        }

        return $this->branch($placements, $byId, MenuTree::ROOT, $locale);
    }

    /**
     * @param list<MenuPlacement>     $placements
     * @param array<int, CmsMenuItem> $byId
     *
     * @return list<array<string, mixed>>
     */
    private function branch(array $placements, array $byId, int $parent, string $locale): array
    {
        $nodes = [];

        foreach ($this->tree->children($placements, $parent) as $id) {
            $item = $byId[$id] ?? null;

            if (null === $item) {
                continue;
            }

            $children = $this->branch($placements, $byId, $id, $locale);
            $target = $this->targets->resolve($item, $locale);

            if (!$target->isUsable()) {
                // Not linkable, but it still holds up a branch: keep the label
                // as a heading rather than promote its children a level up.
                if ('' === $target->label || [] === $children) {
                    continue;
                }

                $nodes[] = $this->node($item, $target->label, null, false, $children);

                continue;
            }

            $nodes[] = $this->node($item, $target->label, $target->url, 1 === $item->getOpenNewTab(), $children);
        }

        return $nodes;
    }

    /**
     * @param list<array<string, mixed>> $children
     *
     * @return array<string, mixed>
     */
    private function node(CmsMenuItem $item, string $label, ?string $url, bool $blank, array $children): array
    {
        $type = MenuTargetType::fromStorage($item->getTargetType());

        return [
            'id' => (int) $item->getId(),
            'label' => $label,
            'url' => $url,
            'blank' => $blank,
            // What the entry points at, for a reader that has to tell a folder
            // from a page: the content slots of the core say it that way.
            'source' => $type->slotSource(),
            'source_id' => match (true) {
                $type->needsTargetId() => (int) $item->getTargetId(),
                MenuTargetType::None === $type => (int) $item->getId(),
                default => null,
            },
            'children' => $children,
            'active' => false,
            'in_trail' => false,
        ];
    }

    /**
     * Flags the entry pointing at the page being served, and its ancestors, so a
     * theme can highlight the current section without comparing URLs itself.
     *
     * @param list<array<string, mixed>> $nodes
     * @param array<string, mixed>       $currentQuery
     *
     * @return list<array<string, mixed>>
     */
    private function markCurrent(array $nodes, string $currentPath, array $currentQuery = [], ?string $currentHost = null): array
    {
        foreach ($nodes as $index => $node) {
            /** @var list<array<string, mixed>> $children */
            $children = $node['children'];
            $children = $this->markCurrent($children, $currentPath, $currentQuery, $currentHost);

            $nodes[$index]['children'] = $children;
            $nodes[$index]['active'] = CurrentEntry::matches($node['url'], $currentPath, $currentQuery, $currentHost);
            $nodes[$index]['in_trail'] = $nodes[$index]['active'] || [] !== array_filter(
                $children,
                static fn (array $child): bool => (bool) $child['in_trail'],
            );
        }

        return $nodes;
    }

    private function currentLocale(): string
    {
        return $this->langService->getLang()?->getLocale() ?? Lang::getDefaultLanguage()->getLocale();
    }
}
