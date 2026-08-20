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

namespace TheliaCMS\Settings;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Hook\Theme\ThemeHookInterface;
use TheliaCMS\Front\BlockStyles;

/**
 * Links the stylesheets of the CMS into the head of every page.
 *
 * Two of them, and the order is the whole point of writing them from one place
 * rather than from two hooks racing on priorities: the socle of the block
 * catalogue first, then the choices the site made in the back office, so a
 * configured colour wins over the default of a block. The stylesheet of the
 * theme is linked before both by the theme itself, so a theme that restyles
 * the catalogue keeps the upper hand.
 *
 * Both are scoped to the CMS markup, so linking them everywhere costs a page
 * nothing and spares the question of which pages hold CMS blocks. The site
 * styles are not linked at all while nothing is configured.
 */
final readonly class SiteStylesThemeHook implements ThemeHookInterface
{
    private const string HOOK = 'layout.head.bottom';

    public function __construct(
        private SiteStyles $styles,
        private BlockStyles $blocks,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return self::HOOK === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        if (self::HOOK !== $hookName) {
            return '';
        }

        $links = [
            $this->urls->generate('cms.block_styles', ['v' => $this->blocks->version()]),
        ];

        if (!$this->styles->typography()->isEmpty()) {
            $links[] = $this->urls->generate('cms.site_styles', ['v' => $this->styles->version()]);
        }

        return implode('', array_map(
            static fn (string $href): string => \sprintf('<link rel="stylesheet" href="%s">', htmlspecialchars($href, \ENT_QUOTES)),
            $links,
        ));
    }
}
