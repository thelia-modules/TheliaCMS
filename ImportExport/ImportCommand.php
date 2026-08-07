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

namespace TheliaCMS\ImportExport;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'thelia_cms:import',
    description: 'Reads a JSON export back into this site.',
)]
final class ImportCommand extends Command
{
    public function __construct(
        private readonly SiteImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'The export file to read.')
            ->addOption('replace', null, InputOption::VALUE_NONE, 'Overwrites the pages, blocks, menus and forms already here instead of leaving them alone.')
            ->addOption('with-settings', null, InputOption::VALUE_NONE, 'Applies the settings of the file: site mode, home page, 404 page, retention, cache.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');

        if (!is_file($file) || !is_readable($file)) {
            $io->error(\sprintf('No readable file at "%s".', $file));

            return Command::FAILURE;
        }

        try {
            $document = SiteDocument::fromJson((string) file_get_contents($file));
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->text(\sprintf(
            'Reading an export written on %s by module version %s.',
            $document->generatedAt() ?? 'an unknown date',
            $document->moduleVersion() ?? 'unknown',
        ));

        $report = $this->importer->import($document, new ImportOptions(
            replace: (bool) $input->getOption('replace'),
            withSettings: (bool) $input->getOption('with-settings'),
        ));

        $io->listing($report->summary());

        foreach ($report->warnings() as $warning) {
            $io->warning($warning);
        }

        $io->success('Import done.');

        return Command::SUCCESS;
    }
}
