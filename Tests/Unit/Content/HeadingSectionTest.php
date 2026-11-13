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

namespace TheliaCMS\Tests\Unit\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;
use TheliaCMS\Content\HeadingSection;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\Pipeline\HeadingChecker;

final class HeadingSectionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function levels(): iterable
    {
        foreach (HeadingSection::LEVELS as $level) {
            yield $level => [$level];
        }
    }

    #[DataProvider('levels')]
    public function testTheHeadingIsDrawnAtTheLevelChosen(string $level): void
    {
        $section = new HeadingSection();

        self::assertSame(
            \sprintf('<div class="cms-heading"><%s class="cms-heading__text">Nos ateliers</%1$s></div>', $level),
            $section->render($section->normalizeProps(['text' => 'Nos ateliers', 'level' => $level]), 'fr_FR'),
        );
    }

    public function testALevelOneOrALevelNotOfferedIsRefused(): void
    {
        foreach (['h1', 'h5', 'p', 'img src=x onerror=alert(1)'] as $level) {
            try {
                (new HeadingSection())->normalizeProps(['text' => 'Titre', 'level' => $level]);
                self::fail(\sprintf('The level "%s" is refused.', $level));
            } catch (InvalidDocumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheTextIsEscapedAndALevelLeftOutIsTheSecond(): void
    {
        $section = new HeadingSection();

        self::assertSame(
            '<div class="cms-heading"><h2 class="cms-heading__text">Prix &lt;b&gt;bas&lt;/b&gt; &amp; service</h2></div>',
            $section->render($section->normalizeProps(['text' => 'Prix <b>bas</b> & service']), 'fr_FR'),
        );
    }

    public function testAHeadingWithoutTextDrawsNothingAndIsRefused(): void
    {
        $section = new HeadingSection();

        self::assertSame('', $section->render(['text' => '  ', 'level' => 'h2'], 'fr_FR'));

        $this->expectException(InvalidDocumentException::class);
        $section->normalizeProps(['text' => '', 'level' => 'h2']);
    }

    public function testTheSampleIsValidAndTheHeadingCheckReadsTheLevels(): void
    {
        $section = new HeadingSection();
        $render = static fn (string $level): string => $section->render(['text' => 'Titre', 'level' => $level], 'fr_FR');

        self::assertSame($section->sample(), $section->normalizeProps($section->sample()));
        self::assertSame([], (new HeadingChecker(new IdentityTranslator()))->check($render('h2').$render('h3'), underTitle: true), 'h2 then h3 under the title of the page.');
        self::assertNotSame([], (new HeadingChecker(new IdentityTranslator()))->check($render('h2').$render('h4'), underTitle: true), 'A level skipped is reported.');
    }

    /**
     * Drawn by a component of the site (ADR 17), the heading sits in the box
     * of the component: the width of a section of its own put it 1rem in.
     */
    public function testAHeadingDrawnByAComponentTakesTheBoxOfTheComponent(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 3).'/assets/blocks.css');

        self::assertMatchesRegularExpression('/\.cms-site \.cms-heading \{\s*margin-inline: 0;\s*width: auto;\s*\}/', $css);
    }
}
