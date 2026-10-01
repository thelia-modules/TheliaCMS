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

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Thelia\Core\Content\Slot\ContentSlotLink;
use Thelia\Core\Content\Slot\ContentSlotResolverInterface;
use Thelia\Core\Content\Slot\ContentSlots;

/**
 * Fills the header and the footer of the theme from the menus of the CMS.
 *
 * A menu takes its slot over as soon as it stores one entry, even when none of
 * its entries resolves in the language being read: the merchant filled it in,
 * and handing the slot back to the folders of the shop would show links nobody
 * chose. An empty menu, the state of a fresh install, leaves the slot to the
 * contents and folders, so activating the module changes nothing on the front
 * until somebody builds a menu.
 *
 * The consent slots are not answered here: the terms of sale stay a content of
 * the shop.
 *
 * Asked before the resolver of the contents, which sits at -100.
 */
#[AsTaggedItem(priority: 100)]
final readonly class CmsContentSlotResolver implements ContentSlotResolverInterface
{
    /** @var array<string, string> slot of the core => code of the menu */
    private const array MENU_OF_SLOT = [
        ContentSlots::HEADER => 'main',
        ContentSlots::FOOTER => 'footer',
    ];

    public function __construct(
        private CmsMenuProvider $menus,
    ) {
    }

    public function resolve(string $slot, string $locale): ?array
    {
        $code = self::MENU_OF_SLOT[$slot] ?? null;

        if (null === $code || !$this->menus->hasEntries($code)) {
            return null;
        }

        return $this->links($this->menus->menu($code, $locale));
    }

    /**
     * Whether an entry is the page being served is left out: the slot is a
     * value a theme may keep, and that flag belongs to one request.
     *
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<ContentSlotLink>
     */
    private function links(array $nodes): array
    {
        return array_map(
            fn (array $node): ContentSlotLink => new ContentSlotLink(
                label: (string) $node['label'],
                url: \is_string($node['url'] ?? null) ? $node['url'] : null,
                source: (string) ($node['source'] ?? MenuTargetType::Url->slotSource()),
                sourceId: \is_int($node['source_id'] ?? null) ? $node['source_id'] : null,
                children: $this->links(\is_array($node['children'] ?? null) ? array_values($node['children']) : []),
                opensInNewWindow: (bool) ($node['blank'] ?? false),
            ),
            $nodes,
        );
    }
}
