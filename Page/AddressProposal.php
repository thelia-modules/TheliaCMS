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

namespace TheliaCMS\Page;

/**
 * The address a page would get, computed without writing anything.
 *
 * `segment` is what the page owns and what its slug stores; `address` is the
 * whole path, under the addresses of its ancestors, with the suffix that keeps
 * it unique when another page or another language already answers on it.
 */
final readonly class AddressProposal
{
    public function __construct(
        public string $segment,
        public string $address,
        public bool $suffixed,
    ) {
    }
}
