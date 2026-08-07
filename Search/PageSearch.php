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

namespace TheliaCMS\Search;

use Propel\Runtime\Propel;

/**
 * Searches the published pages of one locale.
 *
 * The query runs against `cms_page_search`, the plain text extracted when a
 * page was published, never against the HTML columns: a full-text index over
 * markup matches tag names and class names, and ranks a page by how much markup
 * it contains.
 *
 * A site with TntSearch installed can index the same rows for a better ranking;
 * this is what answers when it is not.
 */
final readonly class PageSearch
{
    public function __construct(
        private SearchExcerpt $excerpt = new SearchExcerpt(),
    ) {
    }

    /**
     * @return array{results: list<SearchResult>, total: int}
     */
    public function find(SearchTerms $terms, string $locale, int $page = 1, int $perPage = 10): array
    {
        if (!$terms->isSearchable()) {
            return ['results' => [], 'total' => 0];
        }

        $connection = Propel::getConnection('TheliaMain');
        $expression = $terms->toBooleanQuery();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // The visibility rules are repeated here rather than filtered in PHP
        // after the fact: paging over rows that are then dropped gives pages
        // with three results and pages with none.
        $where = <<<'SQL'
            FROM cms_page_search s
            INNER JOIN cms_page p ON p.id = s.page_id
            INNER JOIN cms_page_content c ON c.page_id = s.page_id AND c.locale = s.locale
            LEFT JOIN cms_page_i18n i ON i.id = s.page_id AND i.locale = s.locale
            WHERE s.locale = :locale
              AND MATCH (s.content) AGAINST (:terms IN BOOLEAN MODE)
              AND p.deleted_at IS NULL
              AND p.visible = 1
              AND (p.publish_at IS NULL OR p.publish_at <= :now)
              AND (p.unpublish_at IS NULL OR p.unpublish_at > :now2)
              AND c.published_at IS NOT NULL
              AND c.published_html IS NOT NULL
              AND COALESCE(i.noindex, 0) = 0
            SQL;

        $bindings = ['locale' => $locale, 'terms' => $expression, 'now' => $now, 'now2' => $now];

        $count = $connection->prepare('SELECT COUNT(*) '.$where);
        $count->execute($bindings);
        $total = (int) $count->fetchColumn();

        if (0 === $total) {
            return ['results' => [], 'total' => 0];
        }

        $offset = max(0, ($page - 1) * $perPage);

        $statement = $connection->prepare(
            'SELECT s.page_id, i.title, s.content, MATCH (s.content) AGAINST (:terms2 IN BOOLEAN MODE) AS score '
            .$where
            .' ORDER BY score DESC, i.title ASC LIMIT '.$perPage.' OFFSET '.$offset
        );
        $statement->execute($bindings + ['terms2' => $expression]);

        $results = [];

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $results[] = new SearchResult(
                pageId: (int) $row['page_id'],
                title: (string) ($row['title'] ?? ''),
                excerpt: $this->excerpt->around((string) $row['content'], $terms),
            );
        }

        return ['results' => $results, 'total' => $total];
    }
}
