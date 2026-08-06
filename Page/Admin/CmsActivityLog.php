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

namespace TheliaCMS\Page\Admin;

use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\AdminLog;
use TheliaCMS\Security\CmsResources;

/**
 * Records CMS changes in the core admin log, so the existing "System logs"
 * screen answers "who unpublished that page?" without a screen of our own.
 *
 * Silently does nothing outside an HTTP request: the same writers run from
 * commands and from the module installer, where there is no admin to blame.
 */
final readonly class CmsActivityLog
{
    public function __construct(
        private RequestStack $requestStack,
        private SecurityContext $securityContext,
    ) {
    }

    public function record(string $action, int $pageId, string $message): void
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        AdminLog::append(
            resource: CmsResources::PAGE,
            action: $action,
            message: $message,
            request: $request,
            adminUser: $this->securityContext->getAdminUser(),
            withRequestContent: false,
            resourceId: $pageId,
        );
    }
}
