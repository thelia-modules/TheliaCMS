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
 * The template a page of a given type is rendered with: the name handed to the
 * front parser, and where it was found.
 */
final readonly class PageTemplate
{
    public function __construct(
        public string $name,
        public PageTemplateSource $source,
    ) {
    }
}
