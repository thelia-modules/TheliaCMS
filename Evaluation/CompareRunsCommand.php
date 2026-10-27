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

namespace TheliaCMS\Evaluation;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Reads a round of runs and says, per brief, how much they agree.
 *
 * A round is a directory holding one directory per brief, each holding the
 * runs of that brief: the output of `thelia_cms:generate --dry-run`, one file
 * per run (`round-0/05/1.txt`). The report is Markdown, to be pasted in the
 * results of the evaluation as it is.
 */
#[AsCommand(
    name: 'thelia_cms:compare-runs',
    description: 'Compares the runs of each brief of a round of the evaluation: structure, shape, length and facts. Calls no API.',
)]
final class CompareRunsCommand extends Command
{
    public function __construct(private readonly RunComparison $comparison)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('round', InputArgument::REQUIRED, 'The directory of the round, one directory per brief inside.')
            ->addOption('details', null, InputOption::VALUE_NONE, 'Lists, per brief, the structures met, the sections whose length varies and the unsteady figures.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $round = rtrim((string) $input->getArgument('round'), '/');

        if (!is_dir($round)) {
            $output->writeln(\sprintf('<error>%s is not a directory.</error>', $round));

            return Command::FAILURE;
        }

        $reports = [];

        foreach (self::briefDirectories($round) as $directory) {
            $runs = [];

            foreach (self::runFiles($directory) as $file) {
                try {
                    $runs[] = Run::fromOutput(basename($file), (string) file_get_contents($file));
                } catch (\InvalidArgumentException $exception) {
                    $output->writeln(\sprintf('<comment>%s/%s left out: %s</comment>', basename($directory), basename($file), $exception->getMessage()));
                }
            }

            if ([] !== $runs) {
                $reports[] = $this->comparison->compare(basename($directory), $runs);
            }
        }

        if ([] === $reports) {
            $output->writeln(\sprintf('<error>No run found under %s.</error>', $round));

            return Command::FAILURE;
        }

        $output->writeln(self::table($reports));

        if ($input->getOption('details')) {
            foreach ($reports as $report) {
                $output->writeln(self::details($report));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private static function briefDirectories(string $round): array
    {
        $directories = glob($round.'/*', \GLOB_ONLYDIR) ?: [];
        sort($directories);

        return $directories;
    }

    /**
     * @return list<string>
     */
    private static function runFiles(string $directory): array
    {
        $files = array_values(array_filter(glob($directory.'/*') ?: [], is_file(...)));
        natsort($files);

        return array_values($files);
    }

    /**
     * @param list<BriefReport> $reports
     */
    private static function table(array $reports): string
    {
        $lines = [
            '| Brief | Runs | Structure | Shape | Widest length spread | Unsteady figures | Cost USD | Turns | Steady |',
            '|---|---|---|---|---|---|---|---|---|',
        ];
        $steady = 0;

        foreach ($reports as $report) {
            $steady += $report->isSteady() ? 1 : 0;
            $lines[] = \sprintf(
                '| %s | %d | %d/%d | %d/%d | %d %% | %s | %s | %s | %s |',
                $report->brief,
                $report->runs,
                max($report->structures),
                $report->runs,
                max($report->shapes),
                $report->runs,
                (int) round($report->widestLengthSpread() * 100),
                [] === $report->unsteadyFacts ? '—' : implode(', ', $report->unsteadyFacts),
                null === $report->averageCostUsd ? '?' : number_format($report->averageCostUsd, 3),
                null === $report->averageTurns ? '?' : number_format($report->averageTurns, 1),
                $report->isSteady() ? 'yes' : 'no',
            );
        }

        $lines[] = '';
        $lines[] = \sprintf(
            '%d of %d briefs steady (structure and shape in %d %% of the runs at least, lengths within %d %%, the same figures in every run).',
            $steady,
            \count($reports),
            (int) round(BriefReport::AGREEMENT_TARGET * 100),
            (int) round(BriefReport::LENGTH_TOLERANCE * 100),
        );

        return implode("\n", $lines);
    }

    private static function details(BriefReport $report): string
    {
        $lines = ['', '## '.$report->brief, '', 'Structures met:', ''];

        foreach ($report->structures as $structure => $count) {
            $lines[] = \sprintf('- %d × `%s`', $count, '' === $structure ? '(no section)' : $structure);
        }

        $varying = array_filter($report->lengthSpread, static fn (float $spread): bool => $spread > BriefReport::LENGTH_TOLERANCE);

        if ([] !== $varying) {
            $lines[] = '';
            $lines[] = 'Sections whose length varies beyond the tolerance:';
            $lines[] = '';

            foreach ($varying as $section => $spread) {
                $lines[] = \sprintf('- `%s`: %d %%', $section, (int) round($spread * 100));
            }
        }

        if ([] !== $report->unsteadyFacts) {
            $lines[] = '';
            $lines[] = 'Figures not written by every run: '.implode(', ', $report->unsteadyFacts);
        }

        return implode("\n", $lines);
    }
}
