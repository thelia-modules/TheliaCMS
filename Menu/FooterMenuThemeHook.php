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

namespace TheliaCMS\Menu;

use Thelia\Core\Hook\Theme\ThemeHookInterface;
use TheliaCMS\TheliaCMS;

/**
 * Puts the footer menu of the CMS in the footer of a theme that knows nothing
 * about the CMS.
 *
 * A companion theme calls `cms_menu('footer')` where it wants it, and gets the
 * nodes as data. Every other theme has no idea the menu exists, so a page of
 * the site is reachable only by its address: on the demonstration site, the
 * footer listed five native contents and no CMS page at all.
 *
 * Off by default and turned on by `footer_menu_hook`, rather than rendered
 * whenever the hook fires: a theme that already renders the menu would show it
 * twice, and no reading of the templates can tell whether it does.
 */
final readonly class FooterMenuThemeHook implements ThemeHookInterface
{
    public const string SETTING = 'footer_menu_hook';

    private const string HOOK = 'layout.footer.top';
    private const string CODE = 'footer';

    public function __construct(
        private CmsMenuProvider $menus,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return self::HOOK === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        if (self::HOOK !== $hookName || !filter_var(TheliaCMS::getConfigValue(self::SETTING, false), \FILTER_VALIDATE_BOOLEAN)) {
            return '';
        }

        $links = $this->links($this->menus->menu(self::CODE));

        if ('' === $links) {
            return '';
        }

        return '<nav class="cms-menu cms-menu--footer">'.$links.'</nav>';
    }

    /**
     * One flat list. The footer of a theme is a column of links, so a nested
     * entry is rendered as a link of its own rather than as a submenu nobody
     * can open down there.
     *
     * @param list<array<string, mixed>> $nodes
     */
    private function links(array $nodes): string
    {
        $items = '';

        foreach ($nodes as $node) {
            $label = trim((string) ($node['label'] ?? ''));
            $url = $node['url'] ?? null;

            if ('' !== $label && \is_string($url) && '' !== $url) {
                $items .= \sprintf(
                    '<li class="cms-menu__item"><a class="cms-menu__link" href="%s"%s>%s</a></li>',
                    htmlspecialchars($url, \ENT_QUOTES),
                    true === ($node['blank'] ?? false) ? ' target="_blank" rel="noopener"' : '',
                    htmlspecialchars($label, \ENT_QUOTES),
                );
            }

            $items .= $this->links($node['children'] ?? []);
        }

        return '' === $items ? '' : '<ul class="cms-menu__list">'.$items.'</ul>';
    }
}
