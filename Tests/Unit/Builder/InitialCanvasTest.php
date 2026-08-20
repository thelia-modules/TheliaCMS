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
use TheliaCMS\Builder\InitialCanvas;

/**
 * What the editor canvas starts from, which decides whether a page survives
 * being opened and saved.
 *
 * The editor writes the canvas back into the HTML and the CSS of the page on
 * every save. So a state where the editor loads nothing and the canvas is left
 * empty is not a display problem: it is the page being erased, one save later,
 * by someone who only came to look.
 */
final class InitialCanvasTest extends TestCase
{
    public function testAPageWithNoProjectStartsFromItsMarkup(): void
    {
        // An import, or a page seeded by the application: HTML and CSS, no
        // editor project.
        self::assertSame(
            '<section>Reprise</section>',
            (new InitialCanvas())->htmlFor(null, '<section>Reprise</section>'),
        );
    }

    public function testAProjectWithNoPageStartsFromTheMarkupToo(): void
    {
        // What a save made while the canvas was empty leaves behind. Loading it
        // drops the markup, and the next save writes the emptiness back.
        self::assertSame(
            '<section>Reprise</section>',
            (new InitialCanvas())->htmlFor('{"pages":[]}', '<section>Reprise</section>'),
        );
    }

    public function testAProjectThatHoldsAPageIsTheAuthority(): void
    {
        // Handing the markup over as well would show the content twice: the
        // editor restores the project into the same canvas.
        self::assertNull(
            (new InitialCanvas())->htmlFor('{"pages":[{"frames":[]}]}', '<section>Reprise</section>'),
        );
    }

    public function testUnreadableProjectDataStillLetsThePageOpenOnItsMarkup(): void
    {
        self::assertSame(
            '<section>Reprise</section>',
            (new InitialCanvas())->htmlFor('{not json', '<section>Reprise</section>'),
        );
    }

    public function testThereIsNothingToStartFromWithoutMarkup(): void
    {
        self::assertNull((new InitialCanvas())->htmlFor(null, null));
        self::assertNull((new InitialCanvas())->htmlFor('{"pages":[]}', ''));
    }
}
