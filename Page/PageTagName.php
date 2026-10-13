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
 * The name of a tag: a label shown as it is, in every language.
 *
 * A name is written the way the editor typed it, less the spaces around it and
 * the runs of spaces inside it: "Recette  d'été " and "Recette d'été" are one
 * tag, and the column, which ignores case, makes "recette" the same tag too.
 */
final class PageTagName
{
    public const int MAX_LENGTH = 100;

    public static function normalize(?string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $name));
    }

    public static function isValid(string $name): bool
    {
        $normalized = self::normalize($name);

        return '' !== $normalized && mb_strlen($normalized) <= self::MAX_LENGTH;
    }
}
