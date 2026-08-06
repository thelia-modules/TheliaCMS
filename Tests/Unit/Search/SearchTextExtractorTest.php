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
use TheliaCMS\Search\SearchTextExtractor;

final class SearchTextExtractorTest extends TestCase
{
    private SearchTextExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new SearchTextExtractor();
    }

    public function testKeepsOnlyTheWordsAVisitorCouldSearchFor(): void
    {
        $html = '<div class="cms-page-content"><h1>Our services</h1>'
            .'<p>We <strong>build</strong> websites.</p></div>';

        self::assertSame('Our services We build websites.', $this->extractor->extract($html));
    }

    /**
     * Without a boundary at the end of a block, stripping the tags glues the
     * last word of one to the first of the next and neither can be found.
     */
    public function testKeepsAWordBoundaryWhereABlockEnds(): void
    {
        $html = '<h1>Title</h1><p>Paragraph</p><ul><li>One</li><li>Two</li></ul>';

        self::assertSame('Title Paragraph One Two', $this->extractor->extract($html));
    }

    public function testTreatsALineBreakAsASpace(): void
    {
        self::assertSame('One Two', $this->extractor->extract('<p>One<br>Two</p>'));
    }

    public function testDoesNotSeparateInlineMarkup(): void
    {
        self::assertSame('Fully qualified', $this->extractor->extract('<p>Fully <em>qualified</em></p>'));
    }

    public function testDecodesEntities(): void
    {
        self::assertSame('Prix & délais « garantis »', $this->extractor->extract(
            '<p>Prix &amp; d&eacute;lais &laquo; garantis &raquo;</p>'
        ));
    }

    public function testCollapsesWhitespace(): void
    {
        self::assertSame('One Two', $this->extractor->extract("<p>One \n\n   \t Two</p>"));
    }

    public function testDropsScriptAndStyleContent(): void
    {
        $text = $this->extractor->extract('<p>Visible</p><script>var hidden = 1;</script><style>p{color:red}</style>');

        self::assertStringContainsString('Visible', $text);
        self::assertStringNotContainsString('var hidden', $text);
        self::assertStringNotContainsString('color:red', $text);
    }

    public function testReturnsAnEmptyStringForEmptyContent(): void
    {
        self::assertSame('', $this->extractor->extract(null));
        self::assertSame('', $this->extractor->extract(''));
        self::assertSame('', $this->extractor->extract('   '));
        self::assertSame('', $this->extractor->extract('<div><span></span></div>'));
    }
}
