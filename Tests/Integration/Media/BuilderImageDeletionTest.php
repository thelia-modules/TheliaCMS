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

use Symfony\Component\DomCrawler\Crawler;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockContent;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Model\LibraryImageQuery;
use TheliaLibrary\Model\Map\LibraryImageTableMap;

/**
 * Deleting an image from the image library of the editor.
 *
 * The editor builds the address of the deletion from the media endpoint the
 * builder screen hands it, so the tests do the same rather than hard-coding
 * the route. An image still shown by a page or a block is refused, as on the
 * media screen: deleting it would leave a broken picture on the site.
 */
final class BuilderImageDeletionTest extends AdminScreenTestCase
{
    public function testThePageBuilderDeletesAnUnusedImage(): void
    {
        $page = $this->createPage('Builder media');
        $image = $this->cmsImage('unused.jpg');

        $status = $this->deleteFromTheBuilder(\sprintf('/admin/cms/pages/%d/builder', $page->getId()), $image);

        self::assertSame(204, $status);
        self::assertFalse($this->exists($image));
    }

    public function testTheBlockBuilderDeletesAnUnusedImage(): void
    {
        $block = $this->block('builder-media');
        $image = $this->cmsImage('unused-in-block.jpg');

        $status = $this->deleteFromTheBuilder(\sprintf('/admin/cms/blocks/%d/builder', $block->getId()), $image);

        self::assertSame(204, $status);
        self::assertFalse($this->exists($image));
    }

    public function testAnImageAPageShowsIsRefused(): void
    {
        $page = $this->createPage('Page with a picture');
        $image = $this->cmsImage('on-a-page.jpg');
        $content = CmsPageContentQuery::create()->filterByPageId($page->getId())->filterByLocale($this->locale())->findOne();
        self::assertNotNull($content);
        $content->setDraftHtml(\sprintf('<img src="/image-library/%d/full/max/0/default.webp" alt="">', $image->getId()))->save();

        $status = $this->deleteFromTheBuilder(\sprintf('/admin/cms/pages/%d/builder', $page->getId()), $image);

        self::assertSame(409, $status);
        self::assertStringContainsString('still used by 1 page(s) or block(s)', $this->errorMessage());
        self::assertTrue($this->exists($image), 'Refused, the image stays.');
    }

    public function testAnImageABlockShowsIsRefused(): void
    {
        $block = $this->block('block-with-a-picture');
        $image = $this->cmsImage('in-a-block.jpg');
        (new CmsBlockContent())
            ->setBlockId($block->getId())
            ->setLocale($this->locale())
            ->setPublishedHtml(\sprintf('<img src="/image-library/%d/full/max/0/default.webp" alt="">', $image->getId()))
            ->save();

        $status = $this->deleteFromTheBuilder(\sprintf('/admin/cms/blocks/%d/builder', $block->getId()), $image);

        self::assertSame(409, $status);
        self::assertStringContainsString('still used by 1 page(s) or block(s)', $this->errorMessage());
        self::assertTrue($this->exists($image), 'Refused, the image stays.');
    }

    public function testAnImageTheCmsDoesNotOwnIsLeftAlone(): void
    {
        $page = $this->createPage('Builder foreign media');
        // Uploaded for a product: the library is shared, the CMS is not.
        $image = new LibraryImage();
        $image->setLocale($this->locale())->setFileName('product.jpg')->setTitle('Product picture');
        $image->save();

        $status = $this->deleteFromTheBuilder(\sprintf('/admin/cms/pages/%d/builder', $page->getId()), $image);

        self::assertSame(404, $status);
        self::assertTrue($this->exists($image));
    }

    public function testTheDeletionStillNeedsTheToken(): void
    {
        $page = $this->createPage('Builder media without token');
        $image = $this->cmsImage('kept.jpg');
        $endpoint = $this->mediaEndpointOf($this->screen(\sprintf('/admin/cms/pages/%d/builder', $page->getId())));

        self::assertSame(403, $this->send('DELETE', $endpoint.'/'.$image->getId(), [], self::XHR));
        self::assertTrue($this->exists($image));
    }

    private function deleteFromTheBuilder(string $builder, LibraryImage $image): int
    {
        $screen = $this->screen($builder);
        $endpoint = $this->mediaEndpointOf($screen);

        return $this->send('DELETE', $endpoint.'/'.$image->getId(), [], [
            ...self::XHR,
            'HTTP_X_CSRF_TOKEN' => $this->tokenOfTheScripts($screen),
        ]);
    }

    private function mediaEndpointOf(Crawler $screen): string
    {
        $editor = $screen->filter('[data-cms-page-builder-media-endpoint-value]');

        self::assertSame(1, $editor->count(), 'The builder hands its editor no media endpoint.');

        return (string) $editor->attr('data-cms-page-builder-media-endpoint-value');
    }

    private function errorMessage(): string
    {
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertIsArray($payload);
        self::assertIsString($payload['error'] ?? null);

        return $payload['error'];
    }

    private function cmsImage(string $fileName): LibraryImage
    {
        $image = new LibraryImage();
        $image->setLocale($this->locale())->setFileName($fileName)->setTitle($fileName);
        $image->save();
        $this->getService(CmsMediaLibrary::class)->tag($image);

        return $image;
    }

    private function block(string $code): CmsBlock
    {
        $block = (new CmsBlock())->setCode($code);
        $block->setLocale($this->locale())->setTitle($code);
        $block->save();

        return $block;
    }

    private function exists(LibraryImage $image): bool
    {
        LibraryImageTableMap::clearInstancePool();

        return null !== LibraryImageQuery::create()->findPk($image->getId());
    }
}
