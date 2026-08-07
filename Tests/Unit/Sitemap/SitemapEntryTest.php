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

namespace TheliaCMS\Tests\Unit\Sitemap;

use PHPUnit\Framework\TestCase;
use TheliaCMS\Sitemap\SitemapEntry;

/**
 * A sitemap is parsed by a machine that gives up on the whole file when one
 * character is wrong, so an unescaped ampersand in one page costs the site
 * every page.
 */
final class SitemapEntryTest extends TestCase
{
    private const string PUBLISHED = '2026-08-07 09:30:00';

    public function testWritesTheAddressAndItsPublicationDate(): void
    {
        $xml = SitemapEntry::toXml('https://example.org/a-page', new \DateTimeImmutable(self::PUBLISHED));

        self::assertStringContainsString('<loc>https://example.org/a-page</loc>', $xml);
        self::assertStringContainsString('<lastmod>2026-08-07T09:30:00', $xml);
    }

    public function testEscapesAnAddressThatWouldBreakTheDocument(): void
    {
        $xml = SitemapEntry::toXml('https://example.org/p?a=1&b=2', new \DateTimeImmutable(self::PUBLISHED));

        self::assertStringContainsString('a=1&amp;b=2', $xml);
        self::assertStringNotContainsString('a=1&b=2', $xml);
        self::assertIsObject(simplexml_load_string($this->wrap($xml)));
    }

    public function testPointsAtEveryLanguageOfThePageIncludingItself(): void
    {
        $xml = SitemapEntry::toXml(
            'https://example.org/mentions-legales',
            new \DateTimeImmutable(self::PUBLISHED),
            ['fr_FR' => 'https://example.org/mentions-legales', 'en_US' => 'https://example.org/legal-notice'],
        );

        self::assertStringContainsString('hreflang="fr-FR"', $xml);
        self::assertStringContainsString('hreflang="en-US"', $xml);
        self::assertSame(2, substr_count($xml, 'xhtml:link'));
    }

    /**
     * The locale is written with a hyphen: `fr_FR` is a PHP locale, `fr-FR` is
     * what the specification asks for and what a crawler reads.
     */
    public function testWritesTheLocaleTheWayTheSpecificationAsksFor(): void
    {
        $xml = SitemapEntry::toXml('https://example.org/p', new \DateTimeImmutable(self::PUBLISHED), ['fr_FR' => 'https://example.org/p']);

        self::assertStringNotContainsString('fr_FR', $xml);
    }

    public function testAPageWithNoTranslationHasNoAlternateAtAll(): void
    {
        $xml = SitemapEntry::toXml('https://example.org/p', new \DateTimeImmutable(self::PUBLISHED));

        self::assertStringNotContainsString('xhtml:link', $xml);
    }

    public function testProducesWellFormedXml(): void
    {
        $xml = SitemapEntry::toXml(
            'https://example.org/p',
            new \DateTimeImmutable(self::PUBLISHED),
            ['fr_FR' => 'https://example.org/p', 'en_US' => 'https://example.org/en/p'],
        );

        self::assertIsObject(simplexml_load_string($this->wrap($xml)));
    }

    private function wrap(string $entry): string
    {
        return '<urlset xmlns:xhtml="http://www.w3.org/1999/xhtml">'.$entry.'</urlset>';
    }
}
