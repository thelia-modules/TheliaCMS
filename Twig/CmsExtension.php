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
use TheliaCMS\Content\Partial\PartialSection;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Locale\AlternateUrls;
use TheliaCMS\Menu\CmsMenuProvider;
use TheliaCMS\Page\Field\FieldFormatter;
use TheliaCMS\Page\PageCard;
use TheliaCMS\Page\PageListQuery;
use TheliaCMS\Page\PageTypeRepository;
use TheliaCMS\Page\PublishedPage;
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
        private readonly SectionCatalog $sections,
        private readonly PageTypeRepository $pageTypes,
        private readonly FieldFormatter $fieldFormatter,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            // The one function that answers markup: a section is markup by
            // definition, and the theme calls it to show a section outside a
            // page — in the toolkit, on a template of its own.
            new TwigFunction('cms_section', $this->section(...), ['is_safe' => ['html']]),
            new TwigFunction('cms_catalogue', $this->catalogue(...)),
            new TwigFunction('cms_menu', $this->menus->menu(...)),
            new TwigFunction('cms_page_alternates', $this->alternates->all(...)),
            new TwigFunction('cms_site_icon', $this->siteIcon(...)),
            // A theme has to be able to ask. On a showcase site the cart and the
            // checkout answer 404, and a header linking to them puts a dead link
            // on every page of the site.
            new TwigFunction('cms_is_showcase', $this->settings->isShowcase(...)),
            new TwigFunction('cms_pages_tagged', $this->pagesTagged(...)),
            new TwigFunction('cms_pages_of_type', $this->pagesOfType(...)),
            new TwigFunction('cms_page_type', $this->pageType(...)),
            new TwigFunction('cms_page_fields', $this->pageFields(...)),
            new TwigFunction('cms_is_home', $this->isHome(...)),
        ];
    }

    /**
     * A section of the catalogue rendered with the given props, or with its
     * sample props when none are given: `cms_section('cms:richtext', {html: '…'})`.
     * An unknown section renders nothing.
     *
     * @param array<string, mixed>|null $props
     */
    public function section(string $name, ?array $props = null, ?string $locale = null): string
    {
        $definition = $this->sections->find($name);

        if (null === $definition) {
            return '';
        }

        return $definition->render(
            $definition->normalizeProps($props ?? $definition->sample()),
            $locale ?? $this->langService->getLocale(),
        );
    }

    /**
     * Every section the site offers, as the toolkit lists them: what
     * to call it, what it is for, and whether it renders on its own or is
     * live content the server fills when the page is served.
     *
     * @return list<array{name: string, label: string, description: string, live: bool}>
     */
    public function catalogue(): array
    {
        $catalogue = [];

        foreach ($this->sections->offered() as $definition) {
            $catalogue[] = [
                'name' => $definition->name(),
                'label' => $definition->label(),
                'description' => $definition->description(),
                'live' => str_starts_with($definition->name(), PartialSection::PREFIX),
            ];
        }

        return $catalogue;
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
        return $this->pages->withTag($tag, $locale ?? $this->currentLocale());
    }

    /**
     * The pages online of a type, the model left out, ordered, filtered and
     * cut as the options say (see PageListQuery):
     * `cms_pages_of_type('recipe', {sort: 'cooking_time', filter: {servings: {min: 4}}, limit: 6})`.
     *
     * @param array<string, mixed> $options sort, direction, filter, limit, offset
     *
     * @return list<PageCard>
     */
    public function pagesOfType(string $pageType, array $options = [], ?string $locale = null): array
    {
        return $this->pages->ofType($pageType, $locale ?? $this->currentLocale(), PageListQuery::fromArray($options));
    }

    /**
     * What a heading of a list says about the type: its name and its
     * description in the language served.
     *
     * @return array{code: string, title: string, description: string|null}
     */
    public function pageType(string $pageType, ?string $locale = null): array
    {
        $locale ??= $this->currentLocale();
        $details = $this->pageTypes->details($pageType);

        return ['code' => $details->code, 'title' => $details->title($locale), 'description' => $details->description($locale)];
    }

    /**
     * The fields of a page or a card that hold a value, each with its label
     * and its value written for a visitor ("1 h 30 min"), in the order of
     * the type. `page.fields.<code>` is the raw value.
     *
     * @return list<array{code: string, label: string, value: int|float|string|bool, text: string}>
     */
    public function pageFields(PublishedPage|PageCard $page): array
    {
        if ([] === $page->fields) {
            return [];
        }

        return $this->fieldFormatter->rows($this->pageTypes->fieldsOf($page->pageType), $page->fields, $page->locale);
    }

    private function currentLocale(): string
    {
        return $this->langService->getLang()?->getLocale() ?? Lang::getDefaultLanguage()->getLocale();
    }

    /**
     * The address of the icon uploaded in the store configuration, or null when
     * there is none and the theme should fall back on its own.
     */
    public function siteIcon(): ?string
    {
        return $this->icon->exists() ? $this->urls->generate('cms.site_icon') : null;
    }

    /**
     * A theme passes the id of a page as it holds it, or nothing on a screen
     * that is not a page: a site with no home page set has none, whatever id.
     */
    public function isHome(int|string|null $id): bool
    {
        $home = (int) TheliaCMS::getConfigValue('home_page_id', 0);

        return $home > 0 && (int) $id === $home;
    }
}
