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

namespace TheliaCMS\Search\Tnt;

use TntSearch\Index\BaseIndex;

/**
 * Offers the pages to TntSearch, on sites that have it.
 *
 * TntSearch is a soft dependency: this class extends one of its own, so it is
 * only ever registered when the module is installed. Everything it indexes is
 * the plain text already extracted at publish time, the same rows the built-in
 * FULLTEXT search reads, so the two never disagree about what a page says.
 */
class CmsPageIndex extends BaseIndex
{
    public function isTranslatable(): bool
    {
        return true;
    }

    public function isGeoIndexable(): bool
    {
        return false;
    }

    public function buildSqlQuery(?int $itemId = null, ?string $locale = null): string
    {
        // Only what a visitor may reach: the bin, the drafts, the pages waiting
        // for their publication date and the ones marked noindex stay out of
        // the index rather than being filtered out of the results afterwards.
        $query = "
            SELECT s.page_id AS id,
                   i.title AS title,
                   s.content AS description
            FROM cms_page_search AS s
            INNER JOIN cms_page AS p ON p.id = s.page_id
            INNER JOIN cms_page_content AS c ON c.page_id = s.page_id AND c.locale = s.locale
            LEFT JOIN cms_page_i18n AS i ON i.id = s.page_id AND i.locale = s.locale
            WHERE s.locale = '".addslashes((string) $locale)."'
              AND p.deleted_at IS NULL
              AND p.visible = 1
              AND (p.publish_at IS NULL OR p.publish_at <= NOW())
              AND (p.unpublish_at IS NULL OR p.unpublish_at > NOW())
              AND c.published_at IS NOT NULL
              AND COALESCE(i.noindex, 0) = 0
        ";

        if (null !== $itemId) {
            $query .= ' AND p.id = '.$itemId;
        }

        return $query;
    }

    public function buildSqlGeoQuery(?int $itemId = null): ?string
    {
        return null;
    }
}
