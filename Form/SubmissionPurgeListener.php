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

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;

/**
 * Ages out the form submissions along with the carts and the admin logs.
 *
 * `maintenance:purge` is the command a Thelia site is already told to schedule,
 * so hooking onto it is what makes retention actually happen: a rule needing a
 * cron entry of its own is a rule half the sites will never run.
 */
final readonly class SubmissionPurgeListener
{
    public function __construct(
        private SubmissionPurger $purger,
    ) {
    }

    #[AsEventListener(event: TheliaEvents::MAINTENANCE_PURGE)]
    public function onMaintenancePurge(MaintenancePurgeEvent $event): void
    {
        $event->addResult(\sprintf(
            '<comment>CMS form submissions past their retention:</comment> <info>%d deleted</info>',
            $this->purger->purge(),
        ));
    }
}
