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

namespace TheliaCMS\Locale;

use Propel\Runtime\Propel;
use TheliaCMS\Catalogue\CatalogueItemType;
use TheliaCMS\Content\Document;
use TheliaCMS\Model\CmsItemContentQuery;
use TheliaCMS\Model\CmsPageContentQuery;

/**
 * Follows a language written from another one: the row of the translation
 * keeps the language it was written from and the fingerprint of the draft it
 * was written from. The translation is out of date once the draft of that
 * language no longer has the same fingerprint, and up to date again when it
 * is put back as it was.
 *
 * The fingerprint is the SHA-256 of the stored draft, the way MySQL's SHA2()
 * computes it, so a list compares it in one query rather than a page at a
 * time.
 */
final readonly class TranslationFreshness
{
    /**
     * A source draft gone (NULL) is not a change worth reporting: there is
     * nothing to write the language from again.
     */
    private const string STALE_PAGES = <<<'SQL'
        FROM cms_page_content t
        JOIN cms_page_content s ON s.page_id = t.page_id AND s.locale = t.translated_from
        WHERE t.translated_from IS NOT NULL
          AND s.draft_document IS NOT NULL
          AND SHA2(s.draft_document, 256) <> t.source_fingerprint
        SQL;

    public static function fingerprint(string $draftDocument): string
    {
        return hash('sha256', $draftDocument);
    }

    public function recordPage(int $pageId, string $locale, string $sourceLocale, string $fingerprint): void
    {
        CmsPageContentQuery::create()->filterByPageId($pageId)->filterByLocale($locale)->findOne()
            ?->setTranslatedFrom($sourceLocale)
            ->setSourceFingerprint($fingerprint)
            ->save();
    }

    public function recordItem(CatalogueItemType $type, int $id, string $locale, string $sourceLocale, string $fingerprint): void
    {
        $this->itemQuery($type, $id)->filterByLocale($locale)->findOne()
            ?->setTranslatedFrom($sourceLocale)
            ->setSourceFingerprint($fingerprint)
            ->save();
    }

    /**
     * Ties this language to the current draft of another: a translation the
     * editor brought up to date by hand, or one written by hand from the
     * start. False when either language holds nothing.
     */
    public function markPageCurrent(int $pageId, string $locale, string $sourceLocale): bool
    {
        $source = CmsPageContentQuery::create()->filterByPageId($pageId)->filterByLocale($sourceLocale)->findOne()?->getDraftDocument();
        $target = CmsPageContentQuery::create()->filterByPageId($pageId)->filterByLocale($locale)->findOne();

        if ($sourceLocale === $locale || !self::holdsSomething($source) || !self::holdsSomething($target?->getDraftDocument())) {
            return false;
        }

        \assert(null !== $source && null !== $target);
        $target->setTranslatedFrom($sourceLocale)->setSourceFingerprint(self::fingerprint($source))->save();

        return true;
    }

    public function markItemCurrent(CatalogueItemType $type, int $id, string $locale, string $sourceLocale): bool
    {
        $source = $this->itemQuery($type, $id)->filterByLocale($sourceLocale)->findOne()?->getDraftDocument();
        $target = $this->itemQuery($type, $id)->filterByLocale($locale)->findOne();

        if ($sourceLocale === $locale || !self::holdsSomething($source) || !self::holdsSomething($target?->getDraftDocument())) {
            return false;
        }

        \assert(null !== $source && null !== $target);
        $target->setTranslatedFrom($sourceLocale)->setSourceFingerprint(self::fingerprint($source))->save();

        return true;
    }

    public function pageLink(int $pageId, string $locale): ?TranslationLink
    {
        $target = CmsPageContentQuery::create()->filterByPageId($pageId)->filterByLocale($locale)->findOne();
        $sourceLocale = (string) $target?->getTranslatedFrom();

        if ('' === $sourceLocale) {
            return null;
        }

        $source = CmsPageContentQuery::create()->filterByPageId($pageId)->filterByLocale($sourceLocale)->findOne()?->getDraftDocument();

        return self::link($sourceLocale, (string) $target?->getSourceFingerprint(), $source);
    }

    public function itemLink(CatalogueItemType $type, int $id, string $locale): ?TranslationLink
    {
        $target = $this->itemQuery($type, $id)->filterByLocale($locale)->findOne();
        $sourceLocale = (string) $target?->getTranslatedFrom();

        if ('' === $sourceLocale) {
            return null;
        }

        $source = $this->itemQuery($type, $id)->filterByLocale($sourceLocale)->findOne()?->getDraftDocument();

        return self::link($sourceLocale, (string) $target?->getSourceFingerprint(), $source);
    }

    /**
     * The pages whose draft in this language was written from a draft that
     * changed since. One query for the whole list.
     *
     * @return array<int, true> keyed by page id
     */
    public function stalePageIds(string $locale): array
    {
        $statement = Propel::getConnection('TheliaMain')->prepare(
            'SELECT t.page_id '.self::STALE_PAGES.' AND t.locale = :locale'
        );
        $statement->execute(['locale' => $locale]);

        return array_fill_keys(array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN)), true);
    }

    /**
     * How many pages of the site are out of date in each language: pages in
     * the bin and the models of the page types are left out, as the dashboard
     * leaves them out of its other figures.
     *
     * @return array<string, int> keyed by locale
     */
    public function stalePageCounts(): array
    {
        $statement = Propel::getConnection('TheliaMain')->prepare(
            'SELECT t.locale, COUNT(*) '.self::STALE_PAGES
            .' AND EXISTS (SELECT 1 FROM cms_page p WHERE p.id = t.page_id AND p.deleted_at IS NULL AND p.model = 0)'
            .' GROUP BY t.locale'
        );
        $statement->execute();

        return array_map('intval', $statement->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    private static function link(string $sourceLocale, string $fingerprint, ?string $source): TranslationLink
    {
        return new TranslationLink($sourceLocale, null !== $source && !hash_equals($fingerprint, self::fingerprint($source)));
    }

    private function itemQuery(CatalogueItemType $type, int $id): CmsItemContentQuery
    {
        return CmsItemContentQuery::create()->filterByItemType($type->value)->filterByItemId($id);
    }

    private static function holdsSomething(?string $json): bool
    {
        $document = Document::fromJson($json);

        return $document instanceof Document && !$document->isEmpty();
    }
}
