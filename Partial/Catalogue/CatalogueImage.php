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

namespace TheliaCMS\Partial\Catalogue;

use Psr\Log\LoggerInterface;
use Thelia\Domain\Media\MediaFacade;
use Thelia\Domain\Media\DTO\ImageProcessDTO;
use Thelia\Model\CategoryImageQuery;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ProductImageQuery;

/**
 * The first picture of a product or a category, resized by the shop itself,
 * for the module's own cards.
 *
 * Through the image processing of the core rather than the filters of a
 * theme: the names of those filters belong to each theme, and the module's
 * cards are the ones drawn when the theme knows nothing of them.
 */
final readonly class CatalogueImage
{
    /** Wide enough for a card on a dense screen, the width it is drawn at doubled. */
    private const int WIDTH = 600;

    public function __construct(
        private MediaFacade $media,
        private LoggerInterface $logger,
    ) {
    }

    public function ofProduct(int $productId, string $locale): ?string
    {
        $image = ProductImageQuery::create()->filterByProductId($productId)->filterByVisible(1)->orderByPosition()->findOne();

        return null === $image ? null : $this->url('product', (string) $image->setLocale($locale)->getFile());
    }

    public function ofCategory(int $categoryId, string $locale): ?string
    {
        $image = CategoryImageQuery::create()->filterByCategoryId($categoryId)->filterByVisible(1)->orderByPosition()->findOne();

        return null === $image ? null : $this->url('category', (string) $image->setLocale($locale)->getFile());
    }

    private function url(string $type, string $file): ?string
    {
        if ('' === $file) {
            return null;
        }

        $library = ConfigQuery::read('images_library_path');
        $base = null === $library ? THELIA_LOCAL_DIR.'media'.\DIRECTORY_SEPARATOR.'images' : THELIA_ROOT.$library;

        // A file missing on disk must not take the card with it: the card is
        // drawn without its picture.
        try {
            return $this->media->processImage(new ImageProcessDTO(\sprintf('%s/%s/%s', $base, $type, $file), $type, width: self::WIDTH))->getFileUrl();
        } catch (\Throwable $throwable) {
            $this->logger->warning('Thelia CMS: no picture for a {type} card: {reason}', ['type' => $type, 'reason' => $throwable->getMessage()]);

            return null;
        }
    }
}
