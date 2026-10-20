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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use TheliaCMS\Partial\Catalogue\CategorySummary;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\TheliaCMS;

/**
 * Several categories of the catalogue on a page, side by side in one row,
 * in the order they were picked: the universes of a shop, the ranges a
 * story leads to.
 *
 * The page stores the ids; the names and the addresses are read again on
 * every visit, and a category hidden since leaves the row without a hole.
 * A theme draws the row itself (`cms/partials/category-strip`) with the
 * cards of its catalogue; the module's own row shows its cards.
 */
final readonly class CategoryStripPartial implements PartialDefinitionInterface
{
    public const string NAME = 'category-strip';

    public const int MIN_CATEGORIES = 2;
    public const int MAX_CATEGORIES = 12;

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
        private CategorySummary $summaries,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Categories in a row');
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/category-strip';
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/category-strip.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::text('heading', $this->trans('Heading'), required: false, help: $this->trans('Shown above the row. Leave empty for no heading.')),
            PartialProp::references(
                'categories',
                $this->trans('Categories'),
                source: $this->urls->generate('admin.cms.partials.sources.categories'),
                itemName: 'category',
                min: self::MIN_CATEGORIES,
                max: self::MAX_CATEGORIES,
                help: $this->trans('Two to twelve categories, shown in this order. Search by name; only categories online are offered, and one that goes offline leaves the row by itself.'),
                searchable: true,
                itemLabel: $this->trans('Category'),
            ),
        ];
    }

    public function context(array $props, string $locale): array
    {
        /** @var list<int> $ids */
        $ids = \is_array($props['categories'] ?? null) ? $props['categories'] : [];

        $byId = [];

        if ([] !== $ids) {
            foreach (CategoryQuery::create()->filterById($ids, Criteria::IN)->filterByVisible(1)->find() as $category) {
                $byId[(int) $category->getId()] = $category;
            }
        }

        $categories = [];

        // In the order they were picked: the database answers in its own.
        foreach ($ids as $id) {
            if (($byId[$id] ?? null) instanceof Category) {
                $categories[] = $this->summaries->of($byId[$id], $locale);
            }
        }

        return [
            'heading' => $props['heading'],
            'categories' => $categories,
            'labels' => [
                'kicker' => $this->trans('Category', $locale),
                'discover' => $this->trans('Discover the category', $locale),
            ],
        ];
    }

    /**
     * Never cached: a catalogue import may write categories without the
     * events of the back office, so no write could drop a stale fragment.
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
