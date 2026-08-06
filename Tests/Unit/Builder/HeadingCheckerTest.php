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

namespace TheliaCMS\Tests\Unit\Builder;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Builder\HeadingChecker;

final class HeadingCheckerTest extends TestCase
{
    private HeadingChecker $checker;

    protected function setUp(): void
    {
        // Messages come back with their placeholders filled in, so a test can
        // assert on what an editor would actually read.
        $this->checker = new HeadingChecker(new class implements TranslatorInterface {
            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return strtr((string) $id, $parameters);
            }

            public function getLocale(): string
            {
                return 'en_US';
            }
        });
    }

    public function testAcceptsAWellFormedOutline(): void
    {
        $html = '<h1>Title</h1><h2>Section</h2><h3>Detail</h3><h2>Another section</h2>';

        self::assertSame([], $this->checker->check($html));
    }

    public function testAcceptsAPageWithNoHeadingAtAll(): void
    {
        self::assertSame([], $this->checker->check('<p>Just a paragraph.</p>'));
    }

    public function testReportsCompetingTopLevelHeadings(): void
    {
        $problems = $this->checker->check('<h1>Title</h1><h1>Other title</h1>');

        self::assertCount(1, $problems);
        self::assertStringContainsString('2 level 1 headings', $problems[0]);
    }

    public function testReportsASkippedLevel(): void
    {
        $problems = $this->checker->check('<h1>Title</h1><h3>Detail</h3>');

        self::assertCount(1, $problems);
        self::assertStringContainsString('from level 1 to level 3', $problems[0]);
    }

    public function testAllowsGoingBackUpSeveralLevels(): void
    {
        $html = '<h1>Title</h1><h2>Section</h2><h3>Detail</h3><h2>Back up</h2>';

        self::assertSame([], $this->checker->check($html));
    }

    public function testReportsEachProblemOnce(): void
    {
        $problems = $this->checker->check('<h1>A</h1><h3>B</h3><h1>C</h1><h3>D</h3>');

        self::assertCount(2, $problems);
        self::assertSame($problems, array_unique($problems));
    }

    public function testReadsHeadingsWhateverTheirAttributesOrCase(): void
    {
        $problems = $this->checker->check('<H1 class="lead">Title</H1><h4 id="x">Detail</h4>');

        self::assertCount(1, $problems);
        self::assertStringContainsString('from level 1 to level 4', $problems[0]);
    }

    /**
     * A heading level does not exist just because a word starts with it.
     */
    public function testIgnoresElementsThatMerelyStartLikeAHeading(): void
    {
        self::assertSame([], $this->checker->check('<h1>Title</h1><header>x</header><hgroup>y</hgroup>'));
    }

    public function testLeavesEmptyContentAlone(): void
    {
        self::assertSame([], $this->checker->check(null));
        self::assertSame([], $this->checker->check(''));
        self::assertSame([], $this->checker->check('   '));
    }
}
