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

namespace TheliaCMS\Twig;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Lang;
use TheliaCMS\Block\Item\ItemType;
use TheliaCMS\Block\Item\PublishedItemBlock;
use TheliaCMS\Block\Item\PublishedItemBlockRepository;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\Http\ShownTags;
use TheliaCMS\Locale\AlternateUrls;
use TheliaCMS\Menu\CmsMenuProvider;
use TheliaCMS\Page\PageCard;
use TheliaCMS\Page\PublishedPageRepository;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\SiteIcon;
use TheliaCMS\TheliaCMS;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The template API of the module.
 *
 * It returns data, never markup: navigation markup belongs to the theme, and a
 * module that returned HTML would have to be fought instead of used.
 */
final class CmsExtension extends AbstractExtension
{
    public function __construct(
        private readonly CmsMenuProvider $menus,
        private readonly AlternateUrls $alternates,
        private readonly SiteIcon $icon,
        private readonly UrlGeneratorInterface $urls,
        private readonly CmsSettings $settings,
        private readonly PublishedPageRepository $pages,
        private readonly LangService $langService,
        private readonly PublishedItemBlockRepository $itemBlocks,
        private readonly ShownTags $shownTags,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_menu', $this->menus->menu(...)),
            new TwigFunction('cms_page_alternates', $this->alternates->all(...)),
            new TwigFunction('cms_site_icon', $this->siteIcon(...)),
            // A theme has to be able to ask. On a showcase site the cart and the
            // checkout answer 404, and a header linking to them puts a dead link
            // on every page of the site.
            new TwigFunction('cms_is_showcase', $this->settings->isShowcase(...)),
            new TwigFunction('cms_pages_tagged', $this->pagesTagged(...)),
            new TwigFunction('cms_item_block', $this->itemBlock(...)),
            new TwigFunction('cms_is_home', $this->isHome(...)),
        ];
    }

    /**
     * The published block of a catalogue item — `cms_item_block('product',
     * product.id)` — in the language being served unless told otherwise, or
     * null when the item has none online in it. Data, not markup: where the
     * theme puts it on the page of the item is the theme's business.
     */
    public function itemBlock(string $type, int $itemId, ?string $locale = null): ?PublishedItemBlock
    {
        $itemType = ItemType::tryFrom($type);

        if (!$itemType instanceof ItemType) {
            return null;
        }

        return $this->itemBlocks->find($itemType, $itemId, $locale ?? $this->currentLocale());
    }

    /**
     * The pages online carrying a tag, the most recently published first, in
     * the language being served unless told otherwise. Data, not markup: what
     * a theme puts around a page is the theme's business.
     *
     * @return list<PageCard>
     */
    public function pagesTagged(string $tag, ?string $locale = null): array
    {
        $this->showsPageList();

        return $this->pages->withTag($tag, $locale ?? $this->currentLocale());
    }

    private function currentLocale(): string
    {
        return $this->langService->getLang()?->getLocale() ?? Lang::getDefaultLanguage()->getLocale();
    }

    /**
     * A list read from a template changes with pages the page that shows it
     * never names, and is dropped with them.
     */
    private function showsPageList(): void
    {
        $this->shownTags->add(CacheTags::PAGE_LISTS);
    }

    /**
     * The address of the icon uploaded in the store configuration, or null when
     * there is none and the theme should fall back on its own.
     */
    public function siteIcon(): ?string
    {
        return $this->icon->exists() ? $this->urls->generate('cms.site_icon') : null;
    }

    public function isHome(string $id): ?bool
    {
        return (int) $id === (int) TheliaCMS::getConfigValue('home_page_id', 0);
    }
}
