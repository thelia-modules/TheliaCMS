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

/**
 * Turns an image URL found in page content back into the stored file.
 *
 * All the publish pipeline needs of the media library, kept apart from it so
 * the rewriting of a page can be exercised without a database.
 */
interface MediaResolver
{
    /**
     * The library image an editor URL points at. Null for anything the library
     * does not own — a remote image, or one that has since been deleted.
     */
    public function fromUrl(string $url): ?MediaFile;
}
