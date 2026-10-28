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

namespace TheliaCMS\Media;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Copies the images of the catalogue into the image library of the CMS, so a
 * page can show them. See CatalogueImageImporter.
 */
#[AsCommand(
    name: 'thelia_cms:media:import-catalogue',
    description: 'Copies the images of the catalogue (products, categories, brands, folders, contents) into the CMS image library.',
)]
final class ImportCatalogueImagesCommand extends Command
{
    public function __construct(
        private readonly CatalogueImageImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('type', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, \sprintf('Only the images of this kind of item, among %s. Repeatable. Defaults to all.', implode(', ', CatalogueImageImporter::TYPES)))
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Counts what would be copied, and copies nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<string> $types */
        $types = $input->getOption('type');
        $unknown = array_diff($types, CatalogueImageImporter::TYPES);

        if ([] !== $unknown) {
            $io->error(\sprintf('Unknown type %s: choose among %s.', implode(', ', $unknown), implode(', ', CatalogueImageImporter::TYPES)));

            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $report = $this->importer->import([] === $types ? CatalogueImageImporter::TYPES : array_values($types), $dryRun);

        if ([] !== $report->missing) {
            $io->section('File not found, not copied');
            $io->listing($report->missing);
        }

        $io->success(\sprintf(
            '%d image(s) %s, %d already in the library.%s',
            $report->imported,
            $dryRun ? 'to copy' : 'copied',
            $report->alreadyThere,
            [] === $report->missing ? '' : \sprintf(' %d without a file.', \count($report->missing)),
        ));

        return Command::SUCCESS;
    }
}
