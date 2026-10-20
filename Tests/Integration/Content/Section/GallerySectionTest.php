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

namespace TheliaCMS\Tests\Integration\Content\Section;

use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\Section\GallerySection;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaLibrary\Model\LibraryImage;

/**
 * A section with a list of items carrying images: the images are resolved by
 * id to the address the library serves them at, never stored as paths.
 */
final class GallerySectionTest extends CmsIntegrationTestCase
{
    public function testTheSampleRendersOneItemPerPictureWithoutAnAddress(): void
    {
        $this->useAnInstalledFrontTheme();
        $section = $this->getService(GallerySection::class);

        $html = $section->render($section->normalizeProps($section->sample()), $this->locale());

        self::assertStringContainsString('<section class="cms-gallery"', $html);
        self::assertSame(3, substr_count($html, '<li class="cms-gallery__item">'));
        self::assertStringContainsString('<img alt="A first picture">', $html, 'An id naming no image gives a picture without an address.');
        self::assertStringContainsString('<figcaption>A caption under it</figcaption>', $html);
    }

    public function testAnImageOfTheLibraryIsResolvedToItsAddress(): void
    {
        $this->useAnInstalledFrontTheme();
        $image = new LibraryImage();
        $image->setLocale($this->locale())->setFileName('gallery-section-test.jpg')->setTitle('Atelier')->setWidth(1200)->setHeight(800);
        $image->save();

        $section = $this->getService(GallerySection::class);
        $html = $section->render($section->normalizeProps([
            'title' => 'Atelier',
            'items' => [['image' => (int) $image->getId(), 'alt' => 'The workshop', 'caption' => '']],
        ]), $this->locale());

        self::assertMatchesRegularExpression('#<img src="[^"]*/image-library/'.$image->getId().'/[^"]*" alt="The workshop" width="1200" height="800">#', $html);
        self::assertStringNotContainsString('<figcaption>', $html);
    }

    public function testAGalleryWithoutPicturesIsRefused(): void
    {
        $this->expectException(InvalidDocumentException::class);
        $this->expectExceptionMessage('"items" must hold between 1 and 12 items.');

        $this->getService(GallerySection::class)->normalizeProps(['title' => 'Vide', 'items' => []]);
    }
}
