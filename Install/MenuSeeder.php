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

namespace TheliaCMS\Install;

use Thelia\Model\LangQuery;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuQuery;

/**
 * Creates the two menus every theme expects to find, empty.
 *
 * A theme calls `cms_menu('main')` unconditionally, so the codes have to exist
 * from the first activation. They are seeded without entries: what belongs in
 * the navigation of a site nobody has written yet is not for us to decide.
 *
 * Replayable: an existing code is left exactly as the site owner left it.
 */
final readonly class MenuSeeder
{
    /**
     * @var array<string, array<string, string>>
     */
    private const array MENUS = [
        'main' => ['en_US' => 'Main menu', 'fr_FR' => 'Menu principal'],
        'footer' => ['en_US' => 'Footer menu', 'fr_FR' => 'Menu du pied de page'],
    ];

    public function seed(): void
    {
        $locales = array_map(
            static fn ($lang): string => (string) $lang->getLocale(),
            iterator_to_array(LangQuery::create()->filterByActive(1)->find(), false)
        );

        foreach (self::MENUS as $code => $titles) {
            if (null !== CmsMenuQuery::create()->findOneByCode($code)) {
                continue;
            }

            $menu = new CmsMenu();
            $menu->setCode($code);

            foreach ($locales as $locale) {
                $menu->setLocale($locale)->setTitle($titles[$locale] ?? $titles['en_US']);
            }

            $menu->save();
        }
    }
}
