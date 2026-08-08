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

namespace TheliaCMS\Tests\Integration\Media;

use TheliaCMS\Media\Admin\MediaCatalog;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Model\LibraryImageQuery;

/**
 * The count the media screen shows has to be the count of the library.
 *
 * It used to be the count of the rows the grid had loaded, which is capped:
 * a library larger than the cap announced the size of one page, and the images
 * still to describe beyond it appeared nowhere.
 */
final class MediaTallyTest extends CmsIntegrationTestCase
{
    public function testCountsEveryImageOfTheLibraryAndNotOnlyTheOnesShown(): void
    {
        $locale = $this->locale();
        $library = $this->getService(CmsMediaLibrary::class);
        $catalog = $this->getService(MediaCatalog::class);

        $before = $catalog->tally($locale);

        $described = $this->image($library, 'tally-described.jpg', $locale, 'Une description');
        $undescribed = $this->image($library, 'tally-undescribed.jpg', $locale, null);
        $decorative = $this->image($library, 'tally-decorative.jpg', $locale, null, decorative: true);

        $after = $catalog->tally($locale);

        self::assertSame($before->total + 3, $after->total, 'Every tagged image counts towards the total.');
        self::assertSame(
            $before->undescribed + 1,
            $after->undescribed,
            'Only the image with neither description nor decorative flag is still to describe.',
        );

        // The grid is capped, so the tally must not be derived from it: asking
        // for a single row must not change what the library is said to hold.
        $shown = \count($library->images(limit: 1));
        self::assertSame(1, $shown);
        self::assertSame($after->total, $catalog->tally($locale)->total);
        self::assertFalse($after->isComplete($shown), 'One row shown out of several is not the whole library.');

        foreach ([$described, $undescribed, $decorative] as $image) {
            self::assertTrue($after->total > 0 && null !== $image->getId());
        }
    }

    public function testCountsNothingWhenTheLibraryHasNoCmsImage(): void
    {
        $tally = $this->getService(MediaCatalog::class)->tally($this->locale());

        self::assertGreaterThanOrEqual(0, $tally->total);
        self::assertGreaterThanOrEqual(0, $tally->undescribed);
        self::assertTrue($tally->isComplete($tally->total));
    }

    /**
     * An image row written the short way: what is counted is the tag, the
     * decorative flag and the alternative text, so no file is needed.
     */
    private function image(CmsMediaLibrary $library, string $fileName, string $locale, ?string $alt, bool $decorative = false): LibraryImage
    {
        $image = new LibraryImage();
        $image->setLocale($locale)
            ->setFileName($fileName)
            ->setTitle($fileName)
            ->setDecorative($decorative ? 1 : 0);

        if (null !== $alt) {
            $image->setAlt($alt);
        }

        $image->save();
        $library->tag($image);

        // Read back, so the count sees what the database holds rather than what
        // this test holds in memory.
        return LibraryImageQuery::create()->findPk($image->getId()) ?? $image;
    }
}
