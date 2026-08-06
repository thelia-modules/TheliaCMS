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

namespace TheliaCMS\Vitrine;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use TheliaCMS\Settings\CmsSettings;

/**
 * Closes the shop paths on a showcase site.
 *
 * A site with no products still answers 200 on its cart and its checkout, which
 * leaves pages a crawler indexes and a visitor can reach. In showcase mode they
 * answer 404 instead.
 *
 * The list is short and deliberate: `/order` is the core checkout, `/cart` and
 * `/checkout` come from the theme. Nothing else is touched — the back office and
 * the API keep working, because a showcase site is one save away from becoming a
 * shop again.
 */
final readonly class ShopRoutesListener
{
    /** @var list<string> */
    private const array CLOSED_PREFIXES = ['/cart', '/order', '/checkout'];

    public function __construct(
        private CmsSettings $settings,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 8)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = rtrim($event->getRequest()->getPathInfo(), '/');

        foreach (self::CLOSED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                // Read last: the mode is a database round trip, and the paths
                // above are a handful of a site's requests.
                if ($this->settings->isShowcase()) {
                    throw new NotFoundHttpException();
                }

                return;
            }
        }
    }
}
