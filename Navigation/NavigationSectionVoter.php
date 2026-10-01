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

namespace TheliaCMS\Navigation;

use Thelia\Core\Template\BackOffice\NavigationSectionVoterInterface;

/**
 * Hides the folder section of the back-office menu: the CMS section links to
 * the folders instead, so the content of the site has one place in the menu.
 *
 * Only the section goes. The folder and content screens, their routes and
 * their permissions are those of the shop, and the news of a site are still
 * contents. No check of the module state is needed: the services of a module
 * are only registered while it is active.
 */
final readonly class NavigationSectionVoter implements NavigationSectionVoterInterface
{
    public const string FOLDER_SECTION = 'folder';

    public function hidesSection(string $section): bool
    {
        return self::FOLDER_SECTION === $section;
    }
}
