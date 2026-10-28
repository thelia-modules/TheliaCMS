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
 * What an import of the catalogue images did: copied, already in the library
 * (the same file, whatever its name), or not found on disk.
 */
final readonly class CatalogueImport
{
    /**
     * @param list<string> $missing the catalogue images whose file is not on disk
     */
    public function __construct(
        public int $imported,
        public int $alreadyThere,
        public array $missing,
    ) {
    }
}
