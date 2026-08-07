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
use TheliaCMS\Builder\EmptyContentChecker;

final class EmptyContentCheckerTest extends TestCase
{
    private EmptyContentChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new EmptyContentChecker();
    }

    public function testANeverEditedPageIsEmpty(): void
    {
        self::assertTrue($this->checker->isEmpty(null));
        self::assertTrue($this->checker->isEmpty(''));
        self::assertTrue($this->checker->isEmpty("  \n\t "));
    }

    public function testACanvasSavedWithoutABlockIsEmpty(): void
    {
        // What the editor exports when nothing was dropped on it.
        self::assertTrue($this->checker->isEmpty('<div class="cms-page-content"></div>'));
        self::assertTrue($this->checker->isEmpty('<div class="cms-page-content"><div></div></div>'));
    }

    public function testAPageOfBlankSpaceIsEmpty(): void
    {
        self::assertTrue($this->checker->isEmpty('<div class="cms-page-content"><p>&nbsp;</p><p> </p></div>'));
    }

    public function testWhatOnlyTheBrowserWouldReadIsNotContent(): void
    {
        // strip_tags() keeps what is inside these two, so they are removed
        // first: neither puts anything on the screen.
        self::assertTrue($this->checker->isEmpty('<style>.cms-hero { color: red }</style>'));
        self::assertTrue($this->checker->isEmpty('<script>document.title = "hello"</script>'));
    }

    public function testTextIsContent(): void
    {
        self::assertFalse($this->checker->isEmpty('<div class="cms-page-content"><p>Hello</p></div>'));
    }

    public function testAPageMadeOfASingleImageIsNotEmpty(): void
    {
        self::assertFalse($this->checker->isEmpty('<div class="cms-page-content"><img src="/media/a.jpg" alt=""></div>'));
        self::assertFalse($this->checker->isEmpty('<div class="cms-page-content"><hr/></div>'));
        self::assertFalse($this->checker->isEmpty('<div class="cms-page-content"><video controls poster="/p.jpg"></video></div>'));
    }

    public function testAPageMadeOfASingleServerRenderedBlockIsNotEmpty(): void
    {
        // A partial carries no text here: what it shows is fetched when the
        // page is displayed.
        self::assertFalse($this->checker->isEmpty('<div data-cms-partial="latest-contents" data-props="{}"></div>'));
    }

    public function testAnElementNameIsNotMistakenForAnother(): void
    {
        // `<hrefsomething>` is not an `<hr>`, and a class named after an
        // element is not an element.
        self::assertTrue($this->checker->isEmpty('<div class="image video"></div>'));
    }
}
