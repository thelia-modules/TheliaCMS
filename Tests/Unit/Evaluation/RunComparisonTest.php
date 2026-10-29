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

namespace TheliaCMS\Tests\Unit\Evaluation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TheliaCMS\Evaluation\CompareRunsCommand;
use TheliaCMS\Evaluation\Run;
use TheliaCMS\Evaluation\RunComparison;

/**
 * The judge of the determinism plan, checked on hand-made runs before any run
 * of the generator is paid for.
 */
final class RunComparisonTest extends TestCase
{
    public function testARunIsReadFromWhatTheCommandPrints(): void
    {
        $run = Run::fromOutput('1.txt', self::printed(self::faq(['Can I rent by the hour?', 'Is parking free?'])));

        self::assertSame(['faq'], array_column($run->sections(), 'id'));
        self::assertSame(3, $run->turns);
        self::assertSame(0.0412, $run->costUsd);
        self::assertSame(12.5, $run->durationSeconds);
    }

    public function testAnOutputWithoutADocumentIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Run::fromOutput('broken.txt', "[ERROR] The generator answered nothing.\n");
    }

    public function testRunsThatOnlyChangeTheirWordsAreSteady(): void
    {
        $runs = [
            self::runOf(self::intro('Rent a studio by the hour, from 2 hours.'), self::faq(['Can I rent by the hour?', 'Is parking free?'])),
            self::runOf(self::intro('Book a studio for 2 hours or more.'), self::faq(['Hourly rental?', 'Parking?'])),
            self::runOf(self::intro('Studios by the hour: 2 hours minimum.'), self::faq(['By the hour?', 'Where to park?'])),
        ];

        $report = (new RunComparison())->compare('05', $runs);

        self::assertSame(1.0, $report->structureAgreement());
        self::assertSame(1.0, $report->shapeAgreement());
        self::assertSame([], $report->unsteadyFacts);
        self::assertTrue($report->isSteady());
    }

    public function testAnotherComponentForTheSameContentIsAnotherStructure(): void
    {
        $faq = self::faq(['Can I rent by the hour?', 'Is parking free?']);
        $asText = ['id' => 'faq', 'component' => 'cms:richtext', 'props' => ['html' => '<h2>Questions</h2><p>Can I rent by the hour?</p>']];

        $report = (new RunComparison())->compare('05', [
            self::runOf($faq), self::runOf($faq), self::runOf($faq), self::runOf($asText), self::runOf($asText),
        ]);

        self::assertSame(0.6, $report->structureAgreement());
        self::assertSame(['Cms:Faq#faq' => 3, 'cms:richtext#faq' => 2], $report->structures);
        self::assertFalse($report->isSteady());
    }

    public function testTheSameStructureWithAnotherNumberOfItemsIsAnotherShape(): void
    {
        $report = (new RunComparison())->compare('05', [
            self::runOf(self::faq(['A?', 'B?'])),
            self::runOf(self::faq(['A?', 'B?', 'C?'])),
        ]);

        self::assertSame(1.0, $report->structureAgreement());
        self::assertSame(0.5, $report->shapeAgreement());
    }

    public function testALinkToAnotherPageIsAnotherShape(): void
    {
        $toContact = ['id' => 'next', 'component' => 'cms:richtext', 'props' => ['html' => '<p>Write to us on <a href="/contact">the contact page</a>.</p>']];
        $toAbout = ['id' => 'next', 'component' => 'cms:richtext', 'props' => ['html' => '<p>Read <a href="/about">who we are</a>.</p>']];

        $report = (new RunComparison())->compare('01', [self::runOf($toContact), self::runOf($toAbout)]);

        self::assertSame(1.0, $report->structureAgreement());
        self::assertSame(0.5, $report->shapeAgreement());
    }

    public function testAFigureSomeRunsWriteAndOthersDoNotIsUnsteady(): void
    {
        $report = (new RunComparison())->compare('07', [
            self::runOf(self::intro('Founded in 2015, 32 people.')),
            self::runOf(self::intro('Founded in 2015, a team of 32.')),
            self::runOf(self::intro('Founded in 2015.')),
        ]);

        self::assertSame(['32'], $report->unsteadyFacts);
        self::assertFalse($report->isSteady());
    }

    public function testTheLengthOfASectionMayVaryWithinTheTolerance(): void
    {
        $report = (new RunComparison())->compare('02', [
            self::runOf(self::intro(str_repeat('a', 100))),
            self::runOf(self::intro(str_repeat('a', 80))),
            self::runOf(self::intro(str_repeat('a', 40))),
        ]);

        self::assertSame(['1#intro' => 0.6], $report->lengthSpread);
        self::assertFalse($report->isSteady());
    }

    public function testTheCommandReportsEveryBriefOfARound(): void
    {
        $round = sys_get_temp_dir().'/theliacms-round-'.bin2hex(random_bytes(4));
        mkdir($round.'/05', 0o777, true);
        mkdir($round.'/06', 0o777, true);

        try {
            file_put_contents($round.'/05/1.txt', self::printed(self::faq(['A?', 'B?'])));
            file_put_contents($round.'/05/2.txt', self::printed(self::faq(['A?', 'B?'])));
            file_put_contents($round.'/06/1.txt', self::printed(self::intro('A short page.')));
            file_put_contents($round.'/06/2.txt', 'nothing here');

            $tester = new CommandTester(new CompareRunsCommand(new RunComparison()));
            $tester->execute(['round' => $round, '--details' => true]);
            $display = $tester->getDisplay();

            self::assertSame(0, $tester->getStatusCode());
            self::assertStringContainsString('| 05 | 2 | 2/2 | 2/2 |', $display);
            self::assertStringContainsString('| 06 | 1 | 1/1 | 1/1 |', $display);
            self::assertStringContainsString('06/2.txt left out', $display);
            self::assertStringContainsString('2 of 2 briefs steady', $display);
            self::assertStringContainsString('2 × `Cms:Faq#faq`', $display);
        } finally {
            foreach (glob($round.'/*/*') ?: [] as $file) {
                unlink($file);
            }

            foreach (glob($round.'/*') ?: [] as $directory) {
                rmdir($directory);
            }

            rmdir($round);
        }
    }

    /**
     * @param array<string, mixed> ...$sections
     */
    private static function runOf(array ...$sections): Run
    {
        return Run::fromOutput('run.txt', self::printed(...$sections));
    }

    /**
     * What `thelia_cms:generate --dry-run` prints for a document of these sections.
     *
     * @param array<string, mixed> ...$sections
     */
    private static function printed(array ...$sections): string
    {
        return json_encode(['version' => 1, 'sections' => array_values($sections)], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)
            ."\n\n // claude-opus-5-5, 3 turn(s), 18000 in / 900 out tokens (17000 cached), 0.0412 USD, 12.5 s\n";
    }

    /**
     * @return array<string, mixed>
     */
    private static function intro(string $text): array
    {
        return ['id' => 'intro', 'component' => 'cms:richtext', 'props' => ['html' => '<p>'.$text.'</p>']];
    }

    /**
     * @param list<string> $questions
     *
     * @return array<string, mixed>
     */
    private static function faq(array $questions): array
    {
        return ['id' => 'faq', 'component' => 'Cms:Faq', 'props' => [
            'title' => 'Questions',
            'items' => array_map(static fn (string $question): array => ['question' => $question, 'answer' => '<p>Yes.</p>'], $questions),
        ]];
    }
}
