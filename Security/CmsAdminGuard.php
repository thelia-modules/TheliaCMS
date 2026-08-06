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

namespace TheliaCMS\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;

/**
 * Guards every route under `/admin/cms`, including the ones imported from the
 * page builder bundle.
 *
 * There is no Symfony firewall on `/admin` in Thelia (the `access_control`
 * section of security.yaml is commented out), so an admin route is only as
 * protected as the check its own controller performs — and the bundle's
 * controllers perform none by design. A listener on the whole prefix means a
 * new route can never ship unguarded by omission.
 */
final readonly class CmsAdminGuard
{
    private const string PREFIX = '/admin/cms';

    /**
     * Section of the CMS a URL belongs to. Anything not listed falls back to
     * the page resource, so a route added later is guarded by default rather
     * than left open.
     *
     * @var array<string, string>
     */
    private const SECTION_RESOURCES = [
        'pages' => CmsResources::PAGE,
        'menus' => CmsResources::MENU,
        'forms' => CmsResources::FORM,
        'media' => CmsResources::MEDIA,
        'settings' => CmsResources::SETTINGS,
        'custom-code' => CmsResources::CUSTOM_CODE,
    ];

    public function __construct(
        private SecurityContext $securityContext,
    ) {
    }

    #[AsEventListener(event: KernelEvents::CONTROLLER, priority: 128)]
    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        if (self::PREFIX !== $path && !str_starts_with($path, self::PREFIX.'/')) {
            return;
        }

        if (null === $this->securityContext->getAdminUser()) {
            throw new AccessDeniedHttpException('Administrator authentication is required.');
        }

        // Read access is the floor; the controller performing a write checks
        // CREATE/UPDATE/DELETE on the same resource.
        if (!$this->securityContext->isGranted(['ADMIN'], [$this->resourceFor($path)], [], [AccessManager::VIEW])) {
            throw new AccessDeniedHttpException('You are not allowed to access the CMS section.');
        }
    }

    private function resourceFor(string $path): string
    {
        $section = explode('/', trim(substr($path, \strlen(self::PREFIX)), '/'))[0] ?? '';

        return self::SECTION_RESOURCES[$section] ?? CmsResources::PAGE;
    }
}
