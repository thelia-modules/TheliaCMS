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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'thelia_cms:export',
    description: 'Writes the pages, blocks, menus, forms and settings of the site to a JSON file.',
)]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly SiteExporter $exporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL, 'Where to write. Left out, the export goes to the standard output.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $document = $this->exporter->export();
        $json = json_encode($document, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        $file = $input->getArgument('file');

        if (null === $file) {
            $output->writeln($json, OutputInterface::OUTPUT_RAW);

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);

        if (false === file_put_contents($file, $json)) {
            $io->error(\sprintf('Could not write to "%s".', $file));

            return Command::FAILURE;
        }

        $io->success(\sprintf(
            '%d page(s), %d block(s), %d menu(s) and %d form(s) written to %s.',
            \count($document['pages']),
            \count($document['blocks']),
            \count($document['menus']),
            \count($document['forms']),
            $file,
        ));

        if ([] !== $document['media']) {
            $io->note(\sprintf(
                'The file names the %d image(s) the content points at, and carries none of them. Upload them to the media library of the other site before importing.',
                \count($document['media']),
            ));
        }

        return Command::SUCCESS;
    }
}
