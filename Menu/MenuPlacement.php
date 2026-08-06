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

namespace TheliaCMS\Menu;

/**
 * Where one entry sits in a menu, with nothing else attached.
 *
 * The tree arithmetic works on these alone, so reordering rules are decided
 * without a database and can be tested as such.
 */
final readonly class MenuPlacement
{
    public function __construct(
        public int $id,
        public int $parent,
        public int $position,
    ) {
    }
}
