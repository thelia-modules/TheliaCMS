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

namespace TheliaCMS\Tests\Unit\Menu;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Menu\CurrentEntry;

final class CurrentEntryTest extends TestCase
{
    /**
     * @param array<string, mixed> $currentQuery
     */
    #[DataProvider('currentEntries')]
    public function testAnEntryIsCurrentOnThePageItPointsAt(string $url, string $path, array $currentQuery): void
    {
        self::assertTrue(CurrentEntry::matches($url, $path, $currentQuery));
    }

    public static function currentEntries(): iterable
    {
        yield 'same path' => ['/about', '/about', []];
        yield 'trailing slash' => ['/about/', '/about', []];
        yield 'absolute url' => ['https://example.org/about', '/about', []];
        yield 'same query' => ['/search?q=recipes', '/search', ['q' => 'recipes']];
        yield 'extra parameters on the page' => ['/search?q=recipes', '/search', ['q' => 'recipes', 'page' => '2']];
    }

    /**
     * @param array<string, mixed> $currentQuery
     */
    #[DataProvider('otherEntries')]
    public function testAnEntryIsNotCurrentElsewhere(mixed $url, string $path, array $currentQuery): void
    {
        self::assertFalse(CurrentEntry::matches($url, $path, $currentQuery));
    }

    public static function otherEntries(): iterable
    {
        yield 'other path' => ['/about', '/contact', []];
        yield 'another search' => ['/search?q=recipes', '/search', ['q' => 'labels']];
        yield 'search without the parameter' => ['/search?q=recipes', '/search', []];
        yield 'anchor only' => ['#prices', '/', []];
        yield 'no url' => [null, '/', []];
    }
}
