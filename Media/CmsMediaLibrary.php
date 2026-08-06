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

namespace TheliaCMS\Media;

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Model\Lang;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Model\LibraryImageQuery;
use TheliaLibrary\Model\LibraryImageTagQuery;
use TheliaLibrary\Model\LibraryTag;
use TheliaLibrary\Model\LibraryTagQuery;
use TheliaLibrary\Service\ImageService;
use TheliaLibrary\Service\LibraryImageTagService;
use TheliaLibrary\TheliaLibrary;

/**
 * The slice of the image library that belongs to the CMS.
 *
 * Images uploaded from the page builder are stored in TheliaLibrary like any
 * other, and marked with a tag: the library is shared with the rest of the
 * shop, but the editor only offers what was uploaded for content pages.
 */
final readonly class CmsMediaLibrary
{
    /** Title of the tag every image uploaded from the builder carries. */
    public const string TAG = 'cms';

    private const string TAG_COLOUR = '#1d4ed8';

    public function __construct(
        private RequestStack $requests,
        private EditLanguage $languages,
        private LibraryImageTagService $imageTags,
        private ImageService $imageService,
    ) {
    }

    /**
     * Locale the image title is written in.
     *
     * Always resolved here and passed explicitly: TheliaLibrary falls back to
     * the admin session, which does not exist on the command line.
     */
    public function locale(): string
    {
        $request = $this->requests->getCurrentRequest();

        return null !== $request
            ? $this->languages->resolve($request)->getLocale()
            : Lang::getDefaultLanguage()->getLocale();
    }

    public function tag(LibraryImage $image): void
    {
        $this->imageTags->associateImage((string) $image->getId(), (string) $this->tagId());
    }

    /**
     * Public URL of an image at its stored size.
     *
     * Resized variants are not built here: the image route reads its size as
     * `width,height` and squares the image unless the ratio-preserving `!` form
     * is used, so the widths of a srcset are composed by the ImageRewriter.
     */
    public function publicUrl(LibraryImage $image): ?string
    {
        $fileName = $image->getFileName();

        if (null === $fileName) {
            return null;
        }

        return $this->imageService->getUrlForImage(
            $image->getId(),
            pathinfo($fileName, \PATHINFO_EXTENSION),
            'full',
            'max',
        );
    }

    /**
     * The library image an editor URL points at, with the dimensions of the
     * stored file. Returns null for anything the library does not own.
     */
    public function fromUrl(string $url): ?MediaFile
    {
        if (1 !== preg_match('#^/image-library/(\d+)/#', $url, $matches)) {
            return null;
        }

        $image = LibraryImageQuery::create()->findPk((int) $matches[1]);

        if (!$image instanceof LibraryImage) {
            return null;
        }

        $fileName = $image->getFileName();

        if (null === $fileName) {
            return null;
        }

        $this->measure($image);

        return new MediaFile(
            id: (int) $image->getId(),
            format: pathinfo($fileName, \PATHINFO_EXTENSION),
            width: $image->getWidth(),
            height: $image->getHeight(),
        );
    }

    /**
     * Reads the size of an image the library has not measured, and records it.
     *
     * Uploads have carried their dimensions since TheliaLibrary 1.4.0; images
     * stored before that have none, and a `<picture>` without width and height
     * makes the page shift as it loads. Measuring once on the way past saves a
     * migration command for what is a handful of files.
     */
    public function measure(LibraryImage $image): void
    {
        $fileName = $image->getFileName();

        if (null === $fileName || (null !== $image->getWidth() && null !== $image->getHeight())) {
            return;
        }

        $path = TheliaLibrary::getImageDirectory().$fileName;
        $dimensions = @getimagesize($path);

        if (false === $dimensions) {
            return;
        }

        $image
            ->setWidth($dimensions[0])
            ->setHeight($dimensions[1])
            ->setFileSize(filesize($path) ?: null)
            ->setMimeType($dimensions['mime'] ?? null)
            ->save();
    }

    /**
     * Images of the library the page builder may offer, most recent first.
     *
     * TheliaLibrary ships no listing service, so the query lives here.
     *
     * @return list<LibraryImage>
     */
    public function images(int $limit = 200): array
    {
        $imageIds = LibraryImageTagQuery::create()
            ->filterByTagId($this->tagId())
            ->select(['ImageId'])
            ->find()
            ->toArray();

        if ([] === $imageIds) {
            return [];
        }

        return array_values(iterator_to_array(
            LibraryImageQuery::create()
                ->filterById($imageIds, Criteria::IN)
                ->orderById(Criteria::DESC)
                ->limit($limit)
                ->find(),
            false,
        ));
    }

    private function tagId(): int
    {
        $tag = LibraryTagQuery::create()->useLibraryTagI18nQuery()->filterByTitle(self::TAG)->endUse()->findOne();

        if (!$tag instanceof LibraryTag) {
            $tag = (new LibraryTag())
                ->setLocale($this->locale())
                ->setTitle(self::TAG)
                ->setColorCode(self::TAG_COLOUR);
            $tag->save();
        }

        return (int) $tag->getId();
    }
}
