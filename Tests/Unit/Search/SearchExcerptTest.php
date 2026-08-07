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
use TheliaCMS\Search\SearchExcerpt;
use TheliaCMS\Search\SearchTerms;

final class SearchExcerptTest extends TestCase
{
    private SearchExcerpt $excerpt;

    protected function setUp(): void
    {
        $this->excerpt = new SearchExcerpt();
    }

    public function testShowsTheTextAroundTheMatch(): void
    {
        $text = str_repeat('avant ', 40).'trouvaille '.str_repeat('apres ', 40);

        $result = $this->excerpt->around($text, SearchTerms::fromInput('trouvaille'));

        self::assertStringContainsString('trouvaille', $result);
        self::assertStringStartsWith('…', $result);
    }

    public function testStartsAtTheBeginningWhenTheMatchIsThere(): void
    {
        $result = $this->excerpt->around('Mentions légales de la boutique', SearchTerms::fromInput('mentions'));

        self::assertStringStartsWith('Mentions', $result);
    }

    /**
     * The excerpt is read by a person, so it stops between two words rather
     * than in the middle of one.
     */
    public function testNeverCutsAWordInHalf(): void
    {
        $text = str_repeat('anticonstitutionnellement ', 40);

        $result = $this->excerpt->around($text, SearchTerms::fromInput('anticonstitutionnellement'));

        self::assertStringNotContainsString('anticonstitution ', $result);
        self::assertStringEndsWith('…', $result);
    }

    public function testFallsBackToTheStartWhenNoWordIsFoundInTheText(): void
    {
        $result = $this->excerpt->around('Un texte quelconque', SearchTerms::fromInput('absent'));

        self::assertStringStartsWith('Un texte', $result);
    }

    public function testHandlesEmptyContent(): void
    {
        self::assertSame('', $this->excerpt->around('', SearchTerms::fromInput('quoi')));
        self::assertSame('', $this->excerpt->around('   ', SearchTerms::fromInput('quoi')));
    }

    public function testCollapsesTheWhitespaceOfTheIndexedText(): void
    {
        $result = $this->excerpt->around("Un   texte \n\n avec des blancs", SearchTerms::fromInput('texte'));

        self::assertSame('Un texte avec des blancs', $result);
    }

    public function testMatchesRegardlessOfCase(): void
    {
        $text = str_repeat('mot ', 40).'CIBLE '.str_repeat('mot ', 40);

        self::assertStringContainsString('CIBLE', $this->excerpt->around($text, SearchTerms::fromInput('cible')));
    }
}
