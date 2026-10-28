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

namespace TheliaCMS\Security;

/**
 * ACL resources owned by the module.
 *
 * They are both declared to the container (so `is_granted` knows them) and
 * seeded into `resource`/`profile_resource` on activation and on every update
 * of the module (a site active before a right existed only gets the update):
 * without the rows, `SecurityContext::isUserGranted()` finds the resource
 * missing from the profile permissions and denies everyone but the super
 * administrator — the "Editor" profile could never be given access.
 */
final class CmsResources
{
    public const string PAGE = 'admin.cms.page';
    public const string MENU = 'admin.cms.menu';
    public const string FORM = 'admin.cms.form';
    public const string MEDIA = 'admin.cms.media';
    public const string SETTINGS = 'admin.cms.settings';
    public const string CUSTOM_CODE = 'admin.cms.custom-code';

    /** Asking the generator to write: it costs money, so it is a right of its own. */
    public const string GENERATE = 'admin.cms.generate';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::PAGE, self::MENU, self::FORM, self::MEDIA, self::SETTINGS, self::CUSTOM_CODE, self::GENERATE];
    }
}
