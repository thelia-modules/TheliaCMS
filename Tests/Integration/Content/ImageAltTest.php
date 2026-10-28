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

namespace TheliaCMS\Tests\Integration\Content;

use TheliaCMS\Content\ColumnsSection;
use TheliaCMS\Content\ImageSection;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaLibrary\Model\LibraryImage;

/**
 * An image of the media library is described there, once for every page: a
 * section draws it with that text, and its own text answers for a picture kept
 * elsewhere or an image not described yet.
 */
final class ImageAltTest extends CmsIntegrationTestCase
{
    public function testAnImageOfTheLibraryIsDrawnWithItsTextInTheLanguage(): void
    {
        $image = $this->image(alt: 'Un fauteuil en velours vert');

        $html = (new ImageSection())->render(['image' => $image->getId(), 'alt' => 'Texte ancien de la section', 'width' => 'narrow'], $this->locale());

        self::assertStringContainsString('alt="Un fauteuil en velours vert"', $html);
    }

    public function testADecorativeImageOfTheLibraryHasAnEmptyText(): void
    {
        $image = $this->image(alt: null, decorative: true);

        $html = (new ImageSection())->render(['image' => $image->getId(), 'alt' => 'Texte de la section', 'width' => 'narrow'], $this->locale());

        self::assertStringContainsString('alt=""', $html);
    }

    public function testTheTextOfTheSectionAnswersForAnImageNotDescribedYetAndForAPictureKeptElsewhere(): void
    {
        $image = $this->image(alt: null);

        self::assertStringContainsString('alt="Texte de la section"', (new ImageSection())->render(['image' => $image->getId(), 'alt' => 'Texte de la section', 'width' => 'narrow'], $this->locale()));
        self::assertStringContainsString('alt="Une tarte"', (new ImageSection())->render(['image' => 'https://example.com/tarte.jpg', 'alt' => 'Une tarte', 'width' => 'narrow'], $this->locale()));
    }

    public function testTheColumnsFollowTheSameRule(): void
    {
        $image = $this->image(alt: 'Une chaise en chêne');

        $html = (new ColumnsSection())->render(['title' => '', 'columns' => [
            ['image' => $image->getId(), 'image_alt' => 'Ancien texte', 'title' => 'Chaise', 'text' => ''],
            ['image' => 'https://example.com/table.jpg', 'image_alt' => 'Une table', 'title' => 'Table', 'text' => ''],
        ]], $this->locale());

        self::assertStringContainsString('alt="Une chaise en chêne"', $html);
        self::assertStringContainsString('alt="Une table"', $html);
    }

    private function image(?string $alt, bool $decorative = false): LibraryImage
    {
        $image = (new LibraryImage())->setFileName(bin2hex(random_bytes(6)).'.jpg')->setDecorative($decorative ? 1 : 0);
        $image->setLocale($this->locale())->setTitle('Image')->setAlt($alt);
        $image->save();

        $this->getService(CmsMediaLibrary::class)->tag($image);

        return $image;
    }
}
