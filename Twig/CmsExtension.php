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

namespace TheliaCMS\Twig;

use TheliaCMS\Locale\AlternateUrls;
use TheliaCMS\Menu\CmsMenuProvider;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The template API of the module.
 *
 * It returns data, never markup: navigation markup belongs to the theme, and a
 * module that returned HTML would have to be fought instead of used.
 */
final class CmsExtension extends AbstractExtension
{
    public function __construct(
        private readonly CmsMenuProvider $menus,
        private readonly AlternateUrls $alternates,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_menu', $this->menus->menu(...)),
            new TwigFunction('cms_page_alternates', $this->alternates->all(...)),
        ];
    }
}
