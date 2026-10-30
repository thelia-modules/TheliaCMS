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
 * A size in bytes as an editor reads it: 850 B, 12.4 KB, 3 MB in English,
 * 850 o, 12,4 Ko, 3 Mo in French (powers of 1024), the decimal mark being the
 * one of the language.
 */
final class PageDocumentSize
{
    private const array UNITS = ['B', 'KB', 'MB', 'GB'];

    private const array FRENCH_UNITS = ['o', 'Ko', 'Mo', 'Go'];

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

        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0 === $unit ? 0 : 1);

        $units = str_starts_with($locale, 'fr') ? self::FRENCH_UNITS : self::UNITS;

        return \sprintf('%s %s', $formatter->format($value), $units[$unit]);
    }
}
