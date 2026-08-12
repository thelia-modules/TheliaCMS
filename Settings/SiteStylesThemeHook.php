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

/**
 * Links the global styles of the site into the head of every page.
 *
 * The stylesheet is scoped to the CMS content, so linking it everywhere costs
 * a page nothing and spares the question of which pages hold CMS blocks. It is
 * not linked at all while nothing is configured.
 */
final readonly class SiteStylesThemeHook implements ThemeHookInterface
{
    private const string HOOK = 'layout.head.bottom';

    public function __construct(
        private SiteStyles $styles,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return self::HOOK === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        if (self::HOOK !== $hookName || $this->styles->typography()->isEmpty()) {
            return '';
        }

        $href = $this->urls->generate('cms.site_styles', ['v' => $this->styles->version()]);

        return \sprintf('<link rel="stylesheet" href="%s">', htmlspecialchars($href, \ENT_QUOTES));
    }
}
