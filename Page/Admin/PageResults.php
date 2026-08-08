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

namespace TheliaCMS\Page\Admin;

/**
 * One page of search results, with the total so the screen can say how many
 * pages match and not only how many it is showing.
 */
final readonly class PageResults
{
    /**
     * @param list<PageListRow> $rows
     */
    public function __construct(
        public array $rows,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }

    public function firstOnPage(): int
    {
        return 0 === $this->total ? 0 : (($this->page - 1) * $this->perPage) + 1;
    }

    public function lastOnPage(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }
}
