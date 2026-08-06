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
 * The locale-specific part of the edit form. The rest of the form writes
 * straight onto the Propel object.
 */
final readonly class PageDraft
{
    public function __construct(
        public string $title,
        public ?string $slug = null,
        public ?string $html = null,
    ) {
    }
}
