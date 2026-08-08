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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TheliaCMS\Locale\AlternateUrls;
use TheliaCMS\Menu\CmsMenuProvider;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\SiteIcon;
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
        private readonly SiteIcon $icon,
        private readonly UrlGeneratorInterface $urls,
        private readonly CmsSettings $settings,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cms_menu', $this->menus->menu(...)),
            new TwigFunction('cms_page_alternates', $this->alternates->all(...)),
            new TwigFunction('cms_site_icon', $this->siteIcon(...)),
            // A theme has to be able to ask. On a showcase site the cart and the
            // checkout answer 404, and a header linking to them puts a dead link
            // on every page of the site.
            new TwigFunction('cms_is_showcase', $this->settings->isShowcase(...)),
        ];
    }

    /**
     * The address of the icon uploaded in the store configuration, or null when
     * there is none and the theme should fall back on its own.
     */
    public function siteIcon(): ?string
    {
        return $this->icon->exists() ? $this->urls->generate('cms.site_icon') : null;
    }
}
