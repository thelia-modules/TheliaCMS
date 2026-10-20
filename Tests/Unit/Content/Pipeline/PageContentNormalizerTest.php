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

namespace TheliaCMS\Tests\Unit\Content\Pipeline;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Content\Pipeline\PageContentNormalizer;

/**
 * A fragment written as a whole document — a `<body>` of its own — is held
 * to the container of the page rather than nesting a body inside it.
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

    public function testLeavesEmptyContentAlone(): void
    {
        self::assertNull($this->normalizer->html(null));
        self::assertSame('  ', $this->normalizer->html('  '));
    }
}
