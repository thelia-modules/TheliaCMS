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

namespace TheliaCMS\Builder;

/**
 * One entry of the block catalogue: what the editor shows in its block panel,
 * and the markup it drops on the page.
 *
 * The markup is the contract with the theme. It carries no styling of its own —
 * only `cms-*` class names the theme stylesheet knows — so a project restyles
 * the whole catalogue by editing CSS, and a page written today still looks
 * right after the theme is reworked.
 */
final readonly class CatalogBlock
{
    public function __construct(
        public string $id,
        public string $label,
        public string $content,
        public string $category,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toEditor(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'content' => $this->content,
            'category' => $this->category,
        ];
    }
}
