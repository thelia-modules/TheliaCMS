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

namespace TheliaCMS\Form;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Deletes the form submissions that have aged out, on demand.
 *
 * The same work runs from `maintenance:purge` (see SubmissionPurgeListener),
 * which is the command a Thelia site is already told to schedule.
 */
#[AsCommand(
    name: 'thelia_cms:forms:purge',
    description: 'Deletes the form submissions kept longer than their form allows.',
)]
final class PurgeSubmissionsCommand extends Command
{
    public function __construct(
        private readonly SubmissionPurger $purger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('<info>%d form submission(s) deleted.</info>', $this->purger->purge()));

        return Command::SUCCESS;
    }
}
