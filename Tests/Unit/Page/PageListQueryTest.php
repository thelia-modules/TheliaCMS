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

namespace TheliaCMS\Tests\Unit\Page;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\Field\FieldDefinitions;
use TheliaCMS\Page\PageListQuery;

/**
 * What a template asks of a list of the pages of a type: options it gets
 * wrong are ignored, filters name the fields of the type, pages without a
 * value for the order go last.
 */
final class PageListQueryTest extends TestCase
{
    public function testOptionsAreReadLeniently(): void
    {
        $query = PageListQuery::fromArray(['sort' => 'cooking_time', 'direction' => 'DESC', 'limit' => '500', 'offset' => -3, 'filter' => 'oops']);

        self::assertSame('cooking_time', $query->sort);
        self::assertTrue($query->descending);
        self::assertSame(PageListQuery::MAX_LIMIT, $query->limit);
        self::assertSame(0, $query->offset);
        self::assertSame([], $query->filters);
        self::assertSame(PageListQuery::SORT_RECENT, PageListQuery::fromArray([])->sort);
    }

    public function testAFilterKeepsThePagesHoldingTheValue(): void
    {
        $query = new PageListQuery(filters: ['difficulty' => 'facile', 'servings' => ['min' => 4]]);

        self::assertTrue($query->matches(['difficulty' => 'facile', 'servings' => 6, 'vegetarian' => null], self::recipe()));
        self::assertFalse($query->matches(['difficulty' => 'facile', 'servings' => 2], self::recipe()));
        self::assertFalse($query->matches(['difficulty' => 'moyen', 'servings' => 6], self::recipe()));
        self::assertFalse($query->matches(['difficulty' => 'facile', 'servings' => null], self::recipe()), 'A page without the value is outside any range.');
    }

    public function testAFilterIsReadAsItsFieldReadsAValue(): void
    {
        self::assertTrue((new PageListQuery(filters: ['servings' => '6']))->matches(['servings' => 6], self::recipe()));
        self::assertTrue((new PageListQuery(filters: ['vegetarian' => false]))->matches(['vegetarian' => null], self::recipe()), 'A box never ticked is a no.');
        self::assertFalse((new PageListQuery(filters: ['vegetarian' => true]))->matches(['vegetarian' => null], self::recipe()));
    }

    public function testAFilterOnAFieldTheTypeLacksKeepsNothing(): void
    {
        self::assertFalse((new PageListQuery(filters: ['colour' => 'red']))->matches(['servings' => 6], self::recipe()));
    }

    public function testPagesAreOrderedByAFieldWithTheEmptyOnesLast(): void
    {
        $rows = [self::row('Tarte', 45), self::row('Gratin', null), self::row('Salade', 10), self::row('Crumble', 45)];

        self::assertSame(['Salade', 'Crumble', 'Tarte', 'Gratin'], self::titles(new PageListQuery(sort: 'cooking_time'), $rows));
        self::assertSame(['Crumble', 'Tarte', 'Salade', 'Gratin'], self::titles(new PageListQuery(sort: 'cooking_time', descending: true), $rows));
    }

    public function testPagesAreOrderedByTitleOrByDate(): void
    {
        $rows = [self::row('Tarte', 1, '2026-10-01'), self::row('crumble', 1, '2026-10-03'), self::row('Gratin', 1, '2026-10-02')];

        self::assertSame(['crumble', 'Gratin', 'Tarte'], self::titles(new PageListQuery(sort: PageListQuery::SORT_TITLE), $rows));
        self::assertSame(['crumble', 'Gratin', 'Tarte'], self::titles(new PageListQuery(), $rows), 'The most recent first.');
        self::assertSame(['Tarte', 'Gratin', 'crumble'], self::titles(new PageListQuery(sort: PageListQuery::SORT_OLDEST), $rows));
        self::assertSame(['crumble', 'Gratin', 'Tarte'], self::titles(new PageListQuery(sort: 'colour'), $rows), 'An order on a field the type lacks is the most recent first.');
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<string>
     */
    private static function titles(PageListQuery $query, array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => $query->compare($a, $b, self::recipe()));

        return array_column($rows, 'title');
    }

    /**
     * @return array{title: string, position: int, published_at: \DateTimeInterface|null, values: array<string, mixed>}
     */
    private static function row(string $title, ?int $cookingTime, string $publishedAt = '2026-10-01'): array
    {
        return ['title' => $title, 'position' => 0, 'published_at' => new \DateTimeImmutable($publishedAt), 'values' => ['cooking_time' => $cookingTime]];
    }

    private static function recipe(): FieldDefinitions
    {
        return FieldDefinitions::fromArray([
            ['code' => 'servings', 'kind' => 'integer'],
            ['code' => 'cooking_time', 'kind' => 'duration'],
            ['code' => 'difficulty', 'kind' => 'choice', 'choices' => ['facile', 'moyen']],
            ['code' => 'vegetarian', 'kind' => 'boolean'],
        ]);
    }
}
