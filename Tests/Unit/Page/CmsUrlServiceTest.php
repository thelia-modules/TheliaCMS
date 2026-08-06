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
use TheliaCMS\Page\CmsUrlService;

/**
 * Only the part of the service that needs no pages: composing a URL from a page
 * tree and reserving it belong to the integration suite.
 */
final class CmsUrlServiceTest extends TestCase
{
    private CmsUrlService $urls;

    protected function setUp(): void
    {
        $this->urls = new CmsUrlService();
    }

    public function testNormalisesASegment(): void
    {
        self::assertSame('nos-engagements', $this->urls->normalizeSegment('Nos engagements'));
    }

    /**
     * The rewriting router runs at priority 1024, ahead of everything: a page
     * slugged `admin` would shadow the back office itself.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function reservedSegments(): iterable
    {
        yield 'back office' => ['admin'];
        yield 'api' => ['api'];
        yield 'assets' => ['assets'];
        yield 'cache' => ['cache'];
        yield 'media' => ['media'];
        yield 'sitemap' => ['sitemap'];
        yield 'robots' => ['robots.txt'];
        yield 'profiler' => ['_profiler'];
        yield 'web debug toolbar' => ['_wdt'];
        yield 'fragments' => ['_fragment'];
        yield 'error pages' => ['_error'];
    }

    #[DataProvider('reservedSegments')]
    public function testRefusesAReservedSegment(string $segment): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($segment);

        $this->urls->normalizeSegment($segment);
    }

    public function testRefusesASegmentThatOnlyBecomesReservedOnceNormalised(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->urls->normalizeSegment('Admin');
    }

    /**
     * The denylist is about the first segment of a path, not about the word
     * appearing anywhere in a title.
     */
    public function testAcceptsASegmentThatMerelyContainsAReservedWord(): void
    {
        self::assertSame('administration', $this->urls->normalizeSegment('Administration'));
        self::assertSame('mediatheque', $this->urls->normalizeSegment('Médiathèque'));
    }

    public function testAcceptsAnEmptySegment(): void
    {
        // The caller falls back to `page-{id}`: refusing here would stop a page
        // being created before it has a title.
        self::assertSame('', $this->urls->normalizeSegment(''));
    }
}
