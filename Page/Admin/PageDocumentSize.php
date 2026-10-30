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

namespace TheliaCMS\Page\Admin;

/**
 * A size in bytes as an editor reads it: 850 B, 12.4 KB, 3 MB (powers of 1024,
 * the decimal mark of the language).
 */
final class PageDocumentSize
{
    private const array UNITS = ['B', 'KB', 'MB', 'GB'];

    private function __construct()
    {
    }

    public static function human(int $bytes, string $locale): string
    {
        $value = (float) max(0, $bytes);
        $unit = 0;

        while ($value >= 1024 && $unit < \count(self::UNITS) - 1) {
            $value /= 1024;
            ++$unit;
        }

        $text = 0 === $unit ? (string) (int) $value : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');

        if (str_starts_with($locale, 'fr') || str_starts_with($locale, 'de') || str_starts_with($locale, 'it') || str_starts_with($locale, 'es')) {
            $text = str_replace('.', ',', $text);
        }

        return $text.' '.self::UNITS[$unit];
    }
}
