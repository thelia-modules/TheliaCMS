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

namespace TheliaCMS\Content\Site;

use TheliaCMS\Content\SectionDefinitionInterface;

/**
 * The sections `cms_component()` may draw (ADR 15, ADR 16, ADR 17): a
 * component of the site kept for the whole site, a component of the theme,
 * or a heading of the site, as long as the catalogue offers it.
 */
interface DrawableSections
{
    /**
     * @param string $name the catalogue name: `Site:<code>`, the name of a component of the theme, or `cms:heading`
     */
    public function find(string $name): ?SectionDefinitionInterface;
}
