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
 * Where the template a page is rendered with comes from, in the order they are
 * tried.
 */
enum PageTemplateSource: string
{
    /** `cmspage-{type}` found by the front parser: the theme, its parents, or another module. */
    case Theme = 'theme';

    /** `cmspage-{type}` shipped with this module. */
    case Module = 'module';

    /** No template for the type: the `cmspage` of the theme. */
    case ThemeFallback = 'theme-fallback';

    /** No template for the type, none in the theme: the `cmspage` of this module. */
    case ModuleFallback = 'module-fallback';

    public function isFallback(): bool
    {
        return self::ThemeFallback === $this || self::ModuleFallback === $this;
    }
}
