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

namespace TheliaCMS\Content;

use TheliaLibrary\Model\LibraryImageQuery;

/**
 * The address an image prop is drawn from, for the sections the module draws
 * itself.
 */
final readonly class ImageSource
{
    /**
     * An address of elsewhere as it is; an image of the library at its full
     * size, in its own format, which the publication turns into a set of
     * widths like any other image of a page.
     */
    public static function of(mixed $image): ?string
    {
        if (\is_string($image) && PropSchema::isExternalImage($image)) {
            return $image;
        }

        $id = (int) $image;

        if ($id <= 0) {
            return null;
        }

        $fileName = LibraryImageQuery::create()->findPk($id)?->getFileName();

        if (null === $fileName) {
            return null;
        }

        return '/image-library/'.$id.'/full/max/0/default.'.strtolower(pathinfo($fileName, \PATHINFO_EXTENSION) ?: 'jpg');
    }

    /**
     * The alternative text an image prop is drawn with. An image of the
     * library is described there, once for every page: its text in the
     * language, or none when it is decorative. The text the section holds
     * answers for a picture kept elsewhere, and for an image of the library
     * not described yet, as sections written before kept it.
     */
    public static function alt(mixed $image, string $sectionAlt, string $locale): string
    {
        $id = \is_string($image) && PropSchema::isExternalImage($image) ? 0 : (int) $image;
        $library = $id > 0 ? LibraryImageQuery::create()->findPk($id) : null;

        if (null === $library) {
            return trim($sectionAlt);
        }

        if (1 === (int) $library->getDecorative()) {
            return '';
        }

        $described = trim((string) $library->setLocale($locale)->getAlt());

        return '' !== $described ? $described : trim($sectionAlt);
    }
}
