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

namespace TheliaCMS\Partial\Definition;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use TheliaCMS\Partial\Catalogue\CatalogueImage;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\TheliaCMS;

/**
 * A category of the catalogue on a page, picked by search: its picture, its
 * name and a link to it.
 *
 * The page stores the id; the name, the address and whether the category is
 * still online are read again on every visit, and the block leaves the page
 * on its own once the category is hidden or deleted.
 */
final readonly class CategoryCardPartial implements PartialDefinitionInterface
{
    public const string NAME = 'category-card';

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
        private CatalogueImage $images,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Category');
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/category-card';
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/category-card.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::reference(
                'category',
                $this->trans('Category'),
                source: $this->urls->generate('admin.cms.partials.sources.categories'),
                help: $this->trans('Search by name. Only categories online are offered. The block leaves the page by itself if the category goes offline.'),
                searchable: true,
            ),
        ];
    }

    public function context(array $props, string $locale): array
    {
        $category = CategoryQuery::create()
            ->filterById((int) $props['category'])
            ->filterByVisible(1)
            ->findOne();

        if (!$category instanceof Category) {
            return ['category' => null];
        }

        $category->setLocale($locale);

        return [
            'category' => [
                'id' => (int) $category->getId(),
                'title' => trim((string) $category->getTitle()),
                'url' => $category->getUrl($locale),
                'image' => $this->images->ofCategory((int) $category->getId(), $locale),
            ],
            // Labels travel with the data: a theme template may ignore them,
            // the fallback cannot reach the catalogue of the module otherwise.
            'labels' => [
                'kicker' => $this->trans('Category', $locale),
                'discover' => $this->trans('Discover the category', $locale),
            ],
        ];
    }

    /**
     * Never cached: a catalogue import may write categories without the events
     * of the back office, so no write could drop a stale fragment, and the card
     * costs one query.
     */
    public function cacheTtl(): ?int
    {
        return null;
    }

    private function trans(string $message, ?string $locale = null): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME, $locale);
    }
}
