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
 * The code of a page type: what picks the `cmspage-{code}` template of a page.
 *
 * The code ends up in a template name, so the format is checked wherever a code
 * comes from outside the form — the database, an import file, a saved page
 * template — not only when an administrator types one.
 */
final class PageTypeCode
{
    /**
     * The type every page starts with, which renders the plain `cmspage`
     * template unless a theme ships `cmspage-default`. Never deleted.
     */
    public const string DEFAULT = 'default';

    public const int MAX_LENGTH = 50;

    /** `D`: without it, `$` also matches before a trailing newline. */
    public const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

    /**
     * PATTERN without its anchors, for a route parameter: a code that is not
     * one never reaches the controller.
     */
    public const string ROUTE_REQUIREMENT = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public static function isValid(string $code): bool
    {
        return \strlen($code) <= self::MAX_LENGTH && 1 === preg_match(self::PATTERN, $code);
    }

    /**
     * A code safe to build a template name from: anything that is not a valid
     * code is the default type.
     */
    public static function orDefault(?string $code): string
    {
        $code = (string) $code;

        return self::isValid($code) ? $code : self::DEFAULT;
    }

    /**
     * Whether the site may drop this type. The default type is what every
     * page falls back to, so it always exists.
     */
    public static function isDeletable(string $code): bool
    {
        return self::DEFAULT !== $code;
    }
}
