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

namespace TheliaCMS\Generation;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Runs the retention of the prompts of the ledger with `maintenance:purge`,
 * the command a Thelia site is already told to schedule.
 */
final readonly class PromptPurgeListener
{
    public function __construct(
        private PromptPurger $purger,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $dryRun = $event->isDryRun();

        $event->addResult(\sprintf(
            '<comment>CMS generator prompts (as the AI log of the core):</comment> <info>%d %s</info>',
            $this->purger->purge($dryRun),
            $dryRun ? 'to empty' : 'emptied',
        ));
    }
}
