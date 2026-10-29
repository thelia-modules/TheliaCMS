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

namespace TheliaCMS\Preview;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A page that showed the draft of an item leaves no trace: not in a search
 * index, not in a shared cache, and its address, which carries the token,
 * is not handed to the sites it links to.
 *
 * After the cache policy of the module (-1024), which would make a page
 * public, and before the shared cache of the core (-1026), which shares no
 * answer marked `no-store`.
 */
final readonly class ItemDraftResponse
{
    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -1025)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || true !== $event->getRequest()->attributes->get(ItemDraftRequest::SERVED_ATTRIBUTE)) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Robots-Tag', 'noindex, nofollow');
        $headers->set('Cache-Control', 'private, no-store, max-age=0');
        $headers->set('Referrer-Policy', 'no-referrer');
    }
}
