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

namespace TheliaCMS\ImportExport;

/**
 * How much of the site an import is allowed to change.
 *
 * Both answers default to no. Importing a starter kit onto a site that already
 * has content is a normal thing to do, and it must not be a way to lose that
 * content by mistake.
 */
final readonly class ImportOptions
{
    public function __construct(
        /** Whether pages, menus, blocks and forms already here are overwritten. */
        public bool $replace = false,
        /** Whether the settings of the file are applied to this site. */
        public bool $withSettings = false,
    ) {
    }
}
