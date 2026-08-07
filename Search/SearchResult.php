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

namespace TheliaCMS\Search;

/**
 * One hit, as a theme needs it: what to write and where to send the visitor.
 * The address is resolved by the template through the URL of the page, so a
 * result carries no markup of its own.
 */
final readonly class SearchResult
{
    public function __construct(
        public int $pageId,
        public string $title,
        public string $excerpt,
    ) {
    }
}
