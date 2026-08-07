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

namespace TheliaCMS\Tests\Unit\Search;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Search\SearchTerms;

/**
 * What a visitor types goes into a MySQL boolean-mode expression. Boolean mode
 * has no escape character, so anything that could be read as an operator has to
 * be gone before the query is built.
 */
final class SearchTermsTest extends TestCase
{
    public function testKeepsTheWordsAndRequiresThemAll(): void
    {
        $terms = SearchTerms::fromInput('mentions legales');

        self::assertSame(['mentions', 'legales'], $terms->words);
        self::assertSame('+mentions +legales*', $terms->toBooleanQuery());
    }

    /**
     * The last word is completed as a prefix, so a visitor who stopped typing
     * mid-word still gets the page they were heading for.
     */
    public function testCompletesTheLastWordAsAPrefix(): void
    {
        self::assertSame('+access*', SearchTerms::fromInput('access')->toBooleanQuery());
    }

    public function testKeepsAccentedLetters(): void
    {
        $terms = SearchTerms::fromInput('déclaration d’accessibilité');

        self::assertContains('déclaration', $terms->words);
        self::assertContains('accessibilité', $terms->words);
    }

    /**
     * `+`, `-`, `*`, `"`, `(`, `<` and `>` are boolean-mode operators. Left in,
     * a search for "C++" is a syntax error and a query built from a visitor's
     * parentheses is a query nobody meant to allow.
     */
    public function testDropsTheBooleanOperators(): void
    {
        $query = SearchTerms::fromInput('+page -legal "exact" (group) >boost <bar')->toBooleanQuery();

        self::assertStringNotContainsString('"', $query);
        self::assertStringNotContainsString('(', $query);
        self::assertStringNotContainsString('>', $query);
        self::assertStringNotContainsString('-legal', $query);
        self::assertSame('+page +legal +exact +group +boost +bar*', $query);
    }

    public function testASearchMadeOnlyOfOperatorsIsNotSearchable(): void
    {
        $terms = SearchTerms::fromInput('*** +++ ---');

        self::assertFalse($terms->isSearchable());
        self::assertSame('', $terms->toBooleanQuery());
    }

    /**
     * Below the server's minimum word length the index has nothing to match, so
     * the page says so rather than reporting an empty result set.
     */
    public function testDropsWordsShorterThanTheIndexCanMatch(): void
    {
        self::assertSame(['legal'], SearchTerms::fromInput('a de legal')->words);
        self::assertFalse(SearchTerms::fromInput('a de')->isSearchable());
    }

    public function testHandlesNothingAtAll(): void
    {
        self::assertFalse(SearchTerms::fromInput(null)->isSearchable());
        self::assertFalse(SearchTerms::fromInput('')->isSearchable());
        self::assertFalse(SearchTerms::fromInput('    ')->isSearchable());
        self::assertSame('', SearchTerms::fromInput(null)->raw);
    }

    public function testRepeatingAWordDoesNotRepeatItInTheQuery(): void
    {
        self::assertSame('+page*', SearchTerms::fromInput('page page page')->toBooleanQuery());
    }

    /**
     * The raw text is echoed back into the search field and into "nothing
     * matches X", so it is bounded like any other input.
     */
    public function testBoundsWhatIsKeptAndEchoedBack(): void
    {
        $terms = SearchTerms::fromInput(str_repeat('a', 50).' '.str_repeat('b', 200));

        self::assertLessThanOrEqual(SearchTerms::MAX_LENGTH, mb_strlen($terms->raw));
    }

    public function testCollapsesWhitespaceIncludingNewlines(): void
    {
        self::assertSame(['legal', 'notice'], SearchTerms::fromInput("legal \n\t  notice")->words);
    }
}
