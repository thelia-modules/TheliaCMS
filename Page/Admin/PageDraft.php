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
 * The locale-specific part of the settings form. The rest of the form writes
 * straight onto the Propel object; the page content belongs to the content
 * screen and travels as a TheliaCMS\Content\Document.
 */
final readonly class PageDraft
{
    /**
     * @param array<string, mixed>|null $fields the values of the fields of the type of the page, by code; null leaves them as they are
     */
    public function __construct(
        public string $title,
        public ?string $slug = null,
        public ?array $fields = null,
    ) {
    }
}
