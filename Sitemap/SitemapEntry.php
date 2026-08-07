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

namespace TheliaCMS\Sitemap;

/**
 * One `<url>` of the sitemap.
 *
 * Written as text rather than through a DOM: the theme owns the document and
 * the module only contributes to it. Everything that goes in is escaped for
 * XML, because a slug or a query string carrying an `&` breaks the whole file,
 * not just its own entry, and a crawler drops a sitemap it cannot parse.
 */
final readonly class SitemapEntry
{
    /**
     * @param array<string, string> $alternates locale => URL, the page in every language it exists in
     */
    public static function toXml(string $url, \DateTimeInterface $publishedAt, array $alternates = []): string
    {
        $lines = ['<url>'];
        $lines[] = '    <loc>'.self::escape($url).'</loc>';

        // The publication date, never the update date of the row: a row is
        // touched by things a reader never sees, and a sitemap claiming every
        // page changed last night is one a crawler learns to distrust.
        $lines[] = '    <lastmod>'.$publishedAt->format('c').'</lastmod>';

        // Every language points at every other one, itself included. A one-way
        // hreflang is ignored.
        foreach ($alternates as $locale => $alternate) {
            $lines[] = \sprintf(
                '    <xhtml:link rel="alternate" hreflang="%s" href="%s"/>',
                self::escape(str_replace('_', '-', $locale)),
                self::escape($alternate),
            );
        }

        $lines[] = '</url>';

        return implode("\n", $lines);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_XML1, 'UTF-8');
    }
}
