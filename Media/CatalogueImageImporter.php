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

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\BrandImageQuery;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentImageQuery;
use Thelia\Model\FolderImageQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Model\Product;
use Thelia\Model\ProductImageQuery;
use TheliaCMS\TheliaCMS;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Service\LibraryImageService;
use TheliaLibrary\TheliaLibrary;

/**
 * Copies the images of the catalogue — products, categories, brands, folders,
 * contents — into the image library of the CMS.
 *
 * A copy: the catalogue keeps its images where the core reads them, and a page
 * points at a library image. Each copy takes the title, the description and
 * the decorative flag of the catalogue image in every language, the title of
 * its item when it has none, and tags naming where it comes from, so the
 * library can be searched by them.
 *
 * Run again, it copies only what is new: a file the library already holds,
 * by its content and whatever its name, is not copied twice.
 */
final readonly class CatalogueImageImporter
{
    public const array TYPES = ['product', 'category', 'brand', 'folder', 'content'];

    private const array QUERIES = [
        'product' => ProductImageQuery::class,
        'category' => CategoryImageQuery::class,
        'brand' => BrandImageQuery::class,
        'folder' => FolderImageQuery::class,
        'content' => ContentImageQuery::class,
    ];

    /** How each image reaches its item: the brand relation is named after its column. */
    private const array ITEMS = [
        'product' => 'getProduct',
        'category' => 'getCategory',
        'brand' => 'getBrandRelatedByBrandId',
        'folder' => 'getFolder',
        'content' => 'getContent',
    ];

    private const array TAGS = [
        'product' => 'Product',
        'category' => 'Category',
        'brand' => 'Brand',
        'folder' => 'Folder',
        'content' => 'Content',
    ];

    public function __construct(
        private LibraryImageService $images,
        private CmsMediaLibrary $library,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<string> $types among TYPES
     */
    public function import(array $types, bool $dryRun = false): CatalogueImport
    {
        $languages = array_values(iterator_to_array(LangQuery::create()->filterByActive(1)->orderByByDefault('desc')->find(), false));
        $known = $this->libraryFingerprints();
        $imported = 0;
        $alreadyThere = 0;
        $missing = [];

        foreach ($types as $type) {
            foreach (self::QUERIES[$type]::create()->orderById()->find() as $image) {
                $texts = $this->textsOf($image, $type, $languages);
                $path = null === $texts['file'] ? null : $image->getUploadDir().\DIRECTORY_SEPARATOR.$texts['file'];

                if (null === $path || !is_file($path)) {
                    $missing[] = \sprintf('%s image #%d: %s', $type, $image->getId(), $path ?? 'no file');

                    continue;
                }

                $fingerprint = sha1_file($path);

                if (isset($known[$fingerprint])) {
                    ++$alreadyThere;

                    continue;
                }

                if (!$dryRun) {
                    $this->copy($path, $texts, 1 === (int) $image->getDecorative(), $this->tagsOf($image, $type, $languages[0]));
                }

                $known[$fingerprint] = true;
                ++$imported;
            }
        }

        return new CatalogueImport($imported, $alreadyThere, $missing);
    }

    /**
     * @param array{file: ?string, titles: array<string, string>, alts: array<string, string>} $texts
     * @param list<string>                                                                     $tags
     */
    private function copy(string $path, array $texts, bool $decorative, array $tags): void
    {
        // The library moves the file it is given into its own directory: it
        // gets a copy, and the catalogue keeps its file.
        $copy = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'theliacms-import-'.bin2hex(random_bytes(6)).'-'.basename($path);
        (new Filesystem())->copy($path, $copy);

        $locales = array_keys($texts['titles'] + $texts['alts']);
        try {
            // Without a title in any language, the image is named after its file.
            $image = $this->images->createImage(new File($copy), basename($path), $locales[0] ?? $this->library->locale());
        } finally {
            // Moved into the library once stored; left behind when refused.
            (new Filesystem())->remove($copy);
        }

        foreach ($locales as $locale) {
            $image->setLocale($locale);

            if (isset($texts['titles'][$locale])) {
                $image->setTitle($texts['titles'][$locale]);
            }

            $image->setAlt($texts['alts'][$locale] ?? null);
        }

        $image->setDecorative($decorative ? 1 : 0);
        $image->save();

        $this->library->retag($image, $tags);
    }

    /**
     * The file, and the title and description in each language, of a
     * catalogue image. The title falls back on the one of its item.
     *
     * @param list<Lang> $languages
     *
     * @return array{file: ?string, titles: array<string, string>, alts: array<string, string>}
     */
    private function textsOf(object $image, string $type, array $languages): array
    {
        $item = $image->{self::ITEMS[$type]}();
        $file = null;
        $titles = [];
        $alts = [];

        foreach ($languages as $language) {
            $locale = (string) $language->getLocale();
            $image->setLocale($locale);
            $item?->setLocale($locale);

            $file ??= '' !== (string) $image->getFile() ? (string) $image->getFile() : null;
            $title = trim((string) $image->getTitle()) ?: trim((string) $item?->getTitle());
            $alt = trim((string) $image->getAlt());

            if ('' !== $title) {
                $titles[$locale] = $title;
            }

            if ('' !== $alt) {
                $alts[$locale] = $alt;
            }
        }

        return ['file' => $file, 'titles' => $titles, 'alts' => $alts];
    }

    /**
     * The kind of item the image comes from, and for a product its default
     * category: what an editor looks for in the library.
     *
     * @return list<string>
     */
    private function tagsOf(object $image, string $type, Lang $language): array
    {
        $locale = (string) $language->getLocale();
        $tags = [$this->translator->trans(self::TAGS[$type], [], TheliaCMS::DOMAIN_NAME, $locale)];
        $item = $image->{self::ITEMS[$type]}();

        if ($item instanceof Product && 0 !== $item->getDefaultCategoryId()) {
            $title = trim((string) CategoryQuery::create()->findPk($item->getDefaultCategoryId())?->setLocale($locale)->getTitle());

            if ('' !== $title) {
                $tags[] = $title;
            }
        }

        return $tags;
    }

    /**
     * The content of every file the CMS library holds.
     *
     * @return array<string, true>
     */
    private function libraryFingerprints(): array
    {
        $fingerprints = [];

        foreach ($this->library->images(limit: \PHP_INT_MAX) as $image) {
            $fingerprint = $this->fingerprintOf($image);

            if (null !== $fingerprint) {
                $fingerprints[$fingerprint] = true;
            }
        }

        return $fingerprints;
    }

    private function fingerprintOf(LibraryImage $image): ?string
    {
        $fileName = $image->getFileName();
        $path = null === $fileName ? null : TheliaLibrary::getImageDirectory().$fileName;

        return null !== $path && is_file($path) ? (sha1_file($path) ?: null) : null;
    }
}
