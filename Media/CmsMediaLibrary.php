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
use Thelia\Model\LangQuery;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Model\LibraryImageI18nQuery;
use TheliaLibrary\Model\LibraryImageQuery;
use TheliaLibrary\Model\LibraryImageTagQuery;
use TheliaLibrary\Model\LibraryTag;
use TheliaLibrary\Model\LibraryTagQuery;
use TheliaLibrary\Service\ImageService;
use TheliaLibrary\Service\LibraryImageTagService;

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
     * Public URL of an image, optionally resized or converted.
     *
     * TheliaLibrary has `getImagePublicUrl()` for this, but it resolves the
     * file name through the *front-office* session language while the builder
     * stores the file under the admin edition language: the two disagree and
     * the URL comes out without an extension. The file name is therefore read
     * here, from the locale that actually holds one.
     *
     * @param int|null $width  in pixels, null for the original size
     * @param int|null $height in pixels, null to keep the ratio
     */
    public function publicUrl(LibraryImage $image, ?int $width = null, ?int $height = null, ?string $format = null): ?string
    {
        $fileName = $this->fileNameOf($image);

        if (null === $fileName) {
            return null;
        }

        $size = null === $width && null === $height ? 'max' : $width.','.$height;

        return $this->imageService->getUrlForImage(
            $image->getId(),
            $format ?? pathinfo($fileName, \PATHINFO_EXTENSION),
            'full',
            $size,
        );
    }

    /**
     * Copies the stored file name onto every active language.
     *
     * A file is not a translation, but TheliaLibrary keeps `file_name` in the
     * i18n table and its own image route reads it through whichever locale the
     * visitor session carries. An image uploaded while editing in French would
     * then 500 for everyone else. Writing the same name everywhere makes the
     * lookup deterministic until the column moves upstream.
     */
    public function shareFileNameAcrossLocales(LibraryImage $image): void
    {
        $fileName = $this->fileNameOf($image);

        if (null === $fileName) {
            return;
        }

        $title = $image->getTitle();

        foreach (LangQuery::create()->filterByActive(1)->find() as $lang) {
            $image->setLocale($lang->getLocale());

            if (null !== $image->getFileName() && '' !== $image->getFileName()) {
                continue;
            }

            $image->setFileName($fileName);

            if (null === $image->getTitle() || '' === $image->getTitle()) {
                $image->setTitle($title);
            }
        }

        $image->save();
    }

    /**
     * The stored file name, whichever translation ended up carrying it.
     *
     * `library_image.file_name` is an i18n column although a file is not a
     * translation: an image uploaded while editing in French has no file name
     * in English. Reported upstream; worked around by falling back.
     */
    public function fileNameOf(LibraryImage $image): ?string
    {
        foreach ([$this->locale(), Lang::getDefaultLanguage()->getLocale()] as $locale) {
            $fileName = $image->setLocale($locale)->getFileName();

            if (null !== $fileName && '' !== $fileName) {
                return $fileName;
            }
        }

        $translation = LibraryImageI18nQuery::create()
            ->filterById($image->getId())
            ->filterByFileName(null, Criteria::ISNOTNULL)
            ->findOne();

        return $translation?->getFileName();
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
