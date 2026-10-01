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

use TheliaCMS\Media\CmsMediaLibrary;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockContent;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Model\LibraryImageQuery;
use TheliaLibrary\Model\Map\LibraryImageTableMap;

/**
 * Deleting an image from the media screen. A reusable block shows its images
 * on every page it is placed on, so an image a block holds is as much in use as
 * one a page holds.
 */
final class MediaScreenDeletionTest extends AdminScreenTestCase
{
    public function testAnImageABlockShowsIsKept(): void
    {
        $image = $this->cmsImage('in-a-block.jpg');
        $block = (new CmsBlock())->setCode('media-screen-block');
        $block->setLocale($this->locale())->setTitle('Block with a picture');
        $block->save();
        (new CmsBlockContent())
            ->setBlockId($block->getId())
            ->setLocale($this->locale())
            ->setDraftHtml(\sprintf('<img src="/image-library/%d/full/max/0/default.webp" alt="">', $image->getId()))
            ->save();

        $this->deleteFromTheScreen($image);

        self::assertTrue($this->exists($image), 'The block still shows the image.');
    }

    public function testAnUnusedImageIsDeleted(): void
    {
        $image = $this->cmsImage('unused.jpg');

        $this->deleteFromTheScreen($image);

        self::assertFalse($this->exists($image));
    }

    private function deleteFromTheScreen(LibraryImage $image): void
    {
        $edit = \sprintf('/admin/cms/media/%d', $image->getId());
        $action = \sprintf('/admin/cms/media/%d/delete', $image->getId());
        $token = $this->tokenOfTheForm($this->screen($edit), $action);

        self::assertSame(302, $this->send('POST', $action, ['_token' => $token]));
    }

    private function cmsImage(string $fileName): LibraryImage
    {
        $image = new LibraryImage();
        $image->setLocale($this->locale())->setFileName($fileName)->setTitle($fileName);
        $image->save();
        $this->getService(CmsMediaLibrary::class)->tag($image);

        return $image;
    }

    private function exists(LibraryImage $image): bool
    {
        LibraryImageTableMap::clearInstancePool();

        return null !== LibraryImageQuery::create()->findPk($image->getId());
    }
}
