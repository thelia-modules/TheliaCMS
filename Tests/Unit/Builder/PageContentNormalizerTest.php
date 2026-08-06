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

namespace TheliaCMS\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Builder\PageContentNormalizer;

/**
 * The editor exports a whole document — a `<body>` styled through `body {}`.
 * Published as it comes, that nests a body inside the page and restyles the
 * entire site, so it is turned into a container of its own first.
 */
final class PageContentNormalizerTest extends TestCase
{
    private PageContentNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new PageContentNormalizer();
    }

    public function testTurnsTheExportedBodyIntoTheContentContainer(): void
    {
        $html = '<body><h1>Hello</h1></body>';

        self::assertSame(
            '<div class="cms-page-content"><h1>Hello</h1></div>',
            $this->normalizer->html($html),
        );
    }

    public function testKeepsTheClassesTheEditorPutOnTheBody(): void
    {
        $html = '<body class="landing dark"><p>Hi</p></body>';

        self::assertSame(
            '<div class="cms-page-content landing dark"><p>Hi</p></div>',
            $this->normalizer->html($html),
        );
    }

    public function testKeepsContentThatIsNotWrappedInABody(): void
    {
        $html = '<div class="cms-page-content"><h1>Hello</h1></div>';

        self::assertSame($html, $this->normalizer->html($html));
    }

    public function testHandlesAMultiLineExport(): void
    {
        $html = "<body>\n  <section>\n    <h2>Title</h2>\n  </section>\n</body>";

        $normalized = (string) $this->normalizer->html($html);

        self::assertStringStartsWith('<div class="cms-page-content">', $normalized);
        self::assertStringEndsWith('</div>', $normalized);
        self::assertStringContainsString('<h2>Title</h2>', $normalized);
        self::assertStringNotContainsString('<body', $normalized);
    }

    public function testRewritesTheBodySelectorInTheStylesheet(): void
    {
        $css = 'body{margin:0}body p{color:red}';

        self::assertSame(
            '.cms-page-content{margin:0}.cms-page-content p{color:red}',
            $this->normalizer->css($css),
        );
    }

    public function testRewritesTheBodySelectorInsideAGroupedRule(): void
    {
        $css = 'h1, body, p{color:red}';

        self::assertSame('h1, .cms-page-content, p{color:red}', $this->normalizer->css($css));
    }

    public function testRewritesTheBodySelectorInsideAMediaQuery(): void
    {
        $css = '@media (min-width:768px){body{padding:2rem}}';

        self::assertSame(
            '@media (min-width:768px){.cms-page-content{padding:2rem}}',
            $this->normalizer->css($css),
        );
    }

    /**
     * `body` is only a selector when it stands alone: a class or property that
     * happens to contain the word is not one.
     */
    public function testLeavesWordsThatMerelyContainBodyAlone(): void
    {
        $css = '.bodybuilder{color:red}.page-body{color:blue}';

        self::assertSame($css, $this->normalizer->css($css));
    }

    public function testLeavesEmptyContentAlone(): void
    {
        self::assertNull($this->normalizer->html(null));
        self::assertSame('', $this->normalizer->html(''));
        self::assertNull($this->normalizer->css(null));
        self::assertSame('  ', $this->normalizer->css('  '));
    }
}
