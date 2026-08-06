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

namespace TheliaCMS\Tests\Unit\Page;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheliaCMS\Page\PageSlugSource;

final class PageSlugSourceTest extends TestCase
{
    private PageSlugSource $slugs;

    protected function setUp(): void
    {
        $this->slugs = new PageSlugSource();
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function titles(): iterable
    {
        yield 'plain words' => ['Our services', 'our-services'];
        yield 'accents' => ['Où télécharger ?', 'ou-telecharger'];
        yield 'ligature' => ['Cœur de métier', 'coeur-de-metier'];
        yield 'german sharp s' => ['Große Auswahl', 'grosse-auswahl'];
        yield 'punctuation' => ['Prix, délais & garanties !', 'prix-delais-garanties'];
        yield 'slashes' => ['A/B testing', 'a-b-testing'];
        yield 'repeated separators' => ['Trop   d’espaces---ici', 'trop-d-espaces-ici'];
        yield 'surrounding separators' => ['  --Hello--  ', 'hello'];
        yield 'digits kept' => ['Offre 2026', 'offre-2026'];
        yield 'already a slug' => ['our-services', 'our-services'];
        yield 'uppercase' => ['NOS ENGAGEMENTS', 'nos-engagements'];
        yield 'emoji' => ['Promo 🎉 du mois', 'promo-du-mois'];
        yield 'non latin script' => ['Ελλάδα', 'ellada'];
        yield 'only punctuation' => ['!!!', ''];
        yield 'empty' => ['', ''];
        yield 'whitespace only' => ["  \n\t ", ''];
    }

    #[DataProvider('titles')]
    public function testTurnsATitleIntoASlug(string $title, string $expected): void
    {
        self::assertSame($expected, $this->slugs->slugify($title));
    }

    public function testLeavesAPathWithinTheColumnLimitAlone(): void
    {
        $url = str_repeat('a', PageSlugSource::MAX_URL_BYTES);

        self::assertSame($url, $this->slugs->truncate($url));
    }

    /**
     * `rewriting_url.url` is a VARBINARY: the limit is bytes, and an over-long
     * path silently loses its tail on insert if it is not cut here.
     */
    public function testCutsAPathDownToTheColumnLimit(): void
    {
        $truncated = $this->slugs->truncate(str_repeat('a', PageSlugSource::MAX_URL_BYTES + 50));

        self::assertSame(PageSlugSource::MAX_URL_BYTES, \strlen($truncated));
    }

    public function testCountsBytesAndNotCharacters(): void
    {
        // Two bytes per character: 200 of them is 400 bytes.
        $truncated = $this->slugs->truncate(str_repeat('é', 200));

        self::assertLessThanOrEqual(PageSlugSource::MAX_URL_BYTES, \strlen($truncated));
        self::assertSame($truncated, mb_convert_encoding($truncated, 'UTF-8', 'UTF-8'), 'the cut left a broken character behind');
    }

    public function testDoesNotLeaveAPathEndingOnASeparator(): void
    {
        $url = str_repeat('a', PageSlugSource::MAX_URL_BYTES - 1).'/bbb';

        $truncated = $this->slugs->truncate($url);

        self::assertStringEndsNotWith('/', $truncated);
        self::assertStringEndsNotWith('-', $truncated);
    }
}
