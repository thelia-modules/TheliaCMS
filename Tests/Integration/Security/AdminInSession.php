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

namespace TheliaCMS\Tests\Integration\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Model\Admin;

/**
 * Puts an administrator in the session of every request the test client sends,
 * the way a login would, without going through the login form.
 *
 * Runs after the session is started and before the guards of the back office
 * read it.
 */
final class AdminInSession implements EventSubscriberInterface
{
    private ?Admin $admin = null;

    public function logIn(Admin $admin): void
    {
        $admin->eraseCredentials();
        $this->admin = $admin;
    }

    public function logOut(): void
    {
        $this->admin = null;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (null === $this->admin || !$event->getRequest()->hasSession(true)) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        $session->set('thelia.admin_user', $this->admin);
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 200]];
    }
}
