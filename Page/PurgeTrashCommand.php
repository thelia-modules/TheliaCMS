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

namespace TheliaCMS\Page;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Empties the bin on demand.
 *
 * The same work runs from `maintenance:purge` (see TrashPurgeListener), which
 * is the command a Thelia site is already told to schedule.
 */
#[AsCommand(
    name: 'thelia_cms:pages:purge-trash',
    description: 'Deletes for good the pages that have been in the bin longer than the site keeps them.',
)]
final class PurgeTrashCommand extends Command
{
    public function __construct(
        private readonly TrashPurger $purger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lists the pages that would go, and deletes nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $due = $this->purger->due();

        if ([] === $due) {
            $io->success('Nothing in the bin is old enough to be deleted.');

            return Command::SUCCESS;
        }

        $io->listing(array_map(
            static fn ($page): string => \sprintf('#%d, binned on %s', $page->getId(), $page->getDeletedAt()?->format('Y-m-d') ?? '?'),
            $due,
        ));

        if ($input->getOption('dry-run')) {
            $io->note(\sprintf('%d page(s) would be deleted. Nothing was.', \count($due)));

            return Command::SUCCESS;
        }

        $io->success(\sprintf('%d page(s) deleted for good.', $this->purger->purge()));

        return Command::SUCCESS;
    }
}
