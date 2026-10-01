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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Tools\TokenProvider;

/**
 * Guards every route under `/admin/cms`, including the ones imported from the
 * page builder bundle.
 *
 * There is no Symfony firewall on `/admin` in Thelia (the `access_control`
 * section of security.yaml is commented out), so an admin route is only as
 * protected as the check its own controller performs — and the bundle's
 * controllers perform none by design. A listener on the whole prefix means a
 * new route can never ship unguarded by omission.
 *
 * The same goes for the token of the back office: every request that is not a
 * read (POST, PUT, PATCH, DELETE) must carry it, in the `_token` field of the
 * body or in the `X-CSRF-Token` header, the way the core and the back-office
 * theme send it.
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
        // Pasting a script tag onto every page of the site is the custom-code
        // permission by any other name, so it is guarded as such.
        'scripts' => CmsResources::CUSTOM_CODE,
    ];

    /**
     * Screens kept out of the back office: the third-party scripts and the site
     * styles. What they configure keeps applying (the saved snippets are still
     * written into the pages, and the custom-code permission still governs free
     * HTML in a page); only the screens are closed, to everybody, so they answer
     * 404 rather than a 403 that would say they exist.
     *
     * @var list<string>
     */
    private const array HIDDEN_PREFIXES = ['/admin/cms/scripts', '/admin/cms/settings/styles'];

    public function __construct(
        private SecurityContext $securityContext,
        private TokenProvider $tokens,
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

        foreach (self::HIDDEN_PREFIXES as $hidden) {
            if ($hidden === $path || str_starts_with($path, $hidden.'/')) {
                throw new NotFoundHttpException();
            }
        }

        if (null === $this->securityContext->getAdminUser()) {
            throw new AccessDeniedHttpException('Administrator authentication is required.');
        }

        // Read access is the floor; the controller performing a write checks
        // CREATE/UPDATE/DELETE on the same resource.
        if (!$this->securityContext->isGranted(['ADMIN'], [$this->resourceFor($path)], [], [AccessManager::VIEW])) {
            throw new AccessDeniedHttpException('You are not allowed to access the CMS section.');
        }

        // Throws TokenAuthenticationException, which the back-office theme
        // answers with a 403 (JSON for a script).
        if (!$event->getRequest()->isMethodSafe()) {
            $this->tokens->checkRequestToken($event->getRequest());
        }
    }

    private function resourceFor(string $path): string
    {
        $section = explode('/', trim(substr($path, \strlen(self::PREFIX)), '/'))[0] ?? '';

        return self::SECTION_RESOURCES[$section] ?? CmsResources::PAGE;
    }
}
