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
     * @return list<array{id: int, label: string, url: string|null, blank: bool, children: list<array<string, mixed>>, active: bool, in_trail: bool}>
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
        return $this->markCurrent($nodes, $request?->getPathInfo() ?? '');
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

                $nodes[] = $this->node($id, $target->label, null, false, $children);

                continue;
            }

            $nodes[] = $this->node($id, $target->label, $target->url, 1 === $item->getOpenNewTab(), $children);
        }

        return $nodes;
    }

    /**
     * @param list<array<string, mixed>> $children
     *
     * @return array<string, mixed>
     */
    private function node(int $id, string $label, ?string $url, bool $blank, array $children): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'url' => $url,
            'blank' => $blank,
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
     *
     * @return list<array<string, mixed>>
     */
    private function markCurrent(array $nodes, string $currentPath): array
    {
        foreach ($nodes as $index => $node) {
            /** @var list<array<string, mixed>> $children */
            $children = $node['children'];
            $children = $this->markCurrent($children, $currentPath);

            $url = $node['url'];
            $path = \is_string($url) ? (string) parse_url($url, \PHP_URL_PATH) : null;

            $nodes[$index]['children'] = $children;
            $nodes[$index]['active'] = null !== $path && '' !== $path && rtrim($path, '/') === rtrim($currentPath, '/');
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
