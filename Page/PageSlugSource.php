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
}
