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
 * Turns a title into one URL segment.
 *
 * Accents are transliterated rather than percent-encoded, and the result is
 * lowercased: `rewriting_url.url` is a VARBINARY column, so MySQL compares it
 * byte for byte and `/Contact` would be a different row from `/contact`.
 */
final readonly class PageSlugSource
{
    /**
     * `rewriting_url.url` is a VARBINARY(255), so the limit counts bytes and an
     * accented slug spends more than one per character.
     */
    public const int MAX_URL_BYTES = 255;

    public function slugify(string $raw): string
    {
        $value = trim($raw);

        if ('' === $value) {
            return '';
        }

        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            $value = $transliterator?->transliterate($value) ?? $value;
        }

        $value = mb_strtolower($value, 'UTF-8');
        $value = (string) preg_replace('/[^a-z0-9]+/u', '-', $value);

        return trim($value, '-');
    }

    /**
     * Cuts a path down to what the column can hold, without leaving it ending
     * on a separator or splitting a multi-byte character in half.
     */
    public function truncate(string $url): string
    {
        if (\strlen($url) <= self::MAX_URL_BYTES) {
            return $url;
        }

        return rtrim(mb_strcut($url, 0, self::MAX_URL_BYTES, 'UTF-8'), '-/');
    }
}
