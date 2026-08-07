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

namespace TheliaCMS\Tests\Unit\ImportExport;

use PHPUnit\Framework\TestCase;
use TheliaCMS\ImportExport\MediaReferences;

final class MediaReferencesTest extends TestCase
{
    public function testCollectsEveryImageOfTheContent(): void
    {
        $html = <<<'HTML'
            <picture>
                <source type="image/webp" srcset="/image-library/12/full/480,!/0/default.webp 480w, /image-library/12/full/960,!/0/default.webp 960w">
                <img src="/image-library/12/full/max/0/default.jpg" alt="">
            </picture>
            <img src="/image-library/40/full/max/0/default.png" alt="">
            HTML;

        $css = '.hero { background-image: url(/image-library/7/full/max/0/default.jpg); }';

        self::assertSame([7, 12, 40], (new MediaReferences())->collect([$html, $css, null, '']));
    }

    /**
     * A number in the text of a page is a number. Only the image route counts.
     */
    public function testIgnoresNumbersThatAreNotImageAddresses(): void
    {
        $html = '<p>Order 12 was shipped. See /media/12/photo.jpg and /image-librarian/9/x.</p>';

        self::assertSame([], (new MediaReferences())->collect([$html]));
    }

    public function testPointsTheContentAtTheImagesOfThisSite(): void
    {
        $html = '<img src="/image-library/12/full/max/0/default.jpg" srcset="/image-library/12/full/480,!/0/default.webp 480w">';

        $rewritten = (new MediaReferences())->remap($html, [12 => 88]);

        self::assertStringContainsString('/image-library/88/full/max/0/default.jpg', $rewritten);
        self::assertStringContainsString('/image-library/88/full/480,!/0/default.webp', $rewritten);
        self::assertStringNotContainsString('/image-library/12/', $rewritten);
    }

    /**
     * An image the importing site does not have keeps its number: the page then
     * shows a broken image, which somebody notices, rather than none at all.
     */
    public function testLeavesAnUnknownImageAsItIs(): void
    {
        $html = '<img src="/image-library/12/full/max/0/default.jpg">';

        self::assertSame($html, (new MediaReferences())->remap($html, [40 => 88]));
    }

    public function testHandlesEmptyContent(): void
    {
        $references = new MediaReferences();

        self::assertNull($references->remap(null, [12 => 88]));
        self::assertSame('', $references->remap('', [12 => 88]));
        self::assertSame('<p>x</p>', $references->remap('<p>x</p>', []));
    }
}
