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

namespace TheliaCMS\Page\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\Map\CmsPageContentTableMap;
use TheliaCMS\Model\Map\CmsPageTableMap;

/**
 * The publication state of a page, expressed for the database.
 *
 * There is already one description of that state, in PageStatus::resolve(), and
 * it is the one the rest of the module trusts. This is a second one, and a second
 * description of the same rule is exactly the kind of thing that agrees with the
 * first for a year and then quietly stops.
 *
 * It exists because a listing that keeps only the drafts has to keep them before
 * it counts and before it cuts a page of fifty rows. Deciding in PHP would mean
 * loading the whole site to show fifty lines of it, which is the problem the
 * filter was added to solve.
 *
 * What keeps the two honest is not care, it is
 * TheliaCMS\Tests\Integration\Page\PageStatusCriteriaTest, which builds a page in
 * each of the five states and asserts that both answers name the same one. Change
 * the rule in either place without the other and it goes red.
 */
final readonly class PageStatusCriteria
{
    /** Alias of the content row joined for the locale being listed. */
    public const string CONTENT_ALIAS = 'state_content';

    private const string JOIN_NAME = 'state_content_join';

    /**
     * Attaches the content row of one locale, left joined: a page with no
     * translation in that locale is a draft there, not an absent page.
     */
    public static function joinContent(CmsPageQuery $query, string $locale): CmsPageQuery
    {
        $join = new Join();
        $join->addExplicitCondition(
            CmsPageTableMap::TABLE_NAME,
            'id',
            null,
            CmsPageContentTableMap::TABLE_NAME,
            'page_id',
            self::CONTENT_ALIAS,
        );
        $join->setJoinType(Criteria::LEFT_JOIN);

        $query->addJoinObject($join, self::JOIN_NAME)
            ->addJoinCondition(self::JOIN_NAME, '`'.self::CONTENT_ALIAS.'`.`locale` = ?', $locale, null, \PDO::PARAM_STR);

        return $query;
    }

    /**
     * Keeps only the pages in one of the given states. An empty list keeps
     * everything, so a filter nobody set does not have to be special-cased by the
     * caller.
     *
     * @param list<PageStatus> $statuses
     */
    public static function restrictTo(CmsPageQuery $query, array $statuses, \DateTimeInterface $now): CmsPageQuery
    {
        if ([] === $statuses) {
            return $query;
        }

        $clauses = array_map(static fn (PageStatus $status): string => '('.self::sqlFor($status, $now).')', $statuses);

        return $query->where(implode(' OR ', $clauses));
    }

    /**
     * The condition that holds for exactly the pages PageStatus::resolve() would
     * name with this state.
     *
     * `$now` is written into the clause rather than bound, because Propel binds
     * one value per `where()` and these clauses need several. It is formatted to
     * `Y-m-d H:i:s`, which can only produce digits, dashes, colons and spaces: no
     * caller can put anything else in there, whatever it passes.
     */
    public static function sqlFor(PageStatus $status, \DateTimeInterface $now): string
    {
        $page = '`'.CmsPageTableMap::TABLE_NAME.'`';
        $content = '`'.self::CONTENT_ALIAS.'`';
        $stamp = "'".$now->format('Y-m-d H:i:s')."'";

        $live = $content.'.`published_at` IS NOT NULL';
        $hidden = '('.$page.'.`visible` <> 1)';
        $expired = '('.$page.'.`unpublish_at` IS NOT NULL AND '.$page.'.`unpublish_at` <= '.$stamp.')';
        $awaited = '('.$page.'.`publish_at` IS NOT NULL AND '.$page.'.`publish_at` > '.$stamp.')';
        $touchedSince = '('.$content.'.`updated_at` IS NOT NULL AND '.$content.'.`updated_at` > '.$content.'.`published_at`)';

        // Same order of decisions as PageStatus::resolve(), so the two can be read
        // side by side.
        $onlineNow = $live.' AND NOT '.$hidden.' AND NOT '.$expired.' AND NOT '.$awaited;

        return match ($status) {
            PageStatus::Draft => $content.'.`published_at` IS NULL',
            PageStatus::Unpublished => $live.' AND ('.$hidden.' OR '.$expired.')',
            PageStatus::Scheduled => $live.' AND NOT '.$hidden.' AND NOT '.$expired.' AND '.$awaited,
            PageStatus::ModifiedSincePublish => $onlineNow.' AND '.$touchedSince,
            PageStatus::Published => $onlineNow.' AND NOT '.$touchedSince,
        };
    }
}
