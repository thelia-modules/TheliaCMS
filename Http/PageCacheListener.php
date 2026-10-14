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

namespace TheliaCMS\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use TheliaCMS\Http\SharedCache\CoreSharedCache;

/**
 * Writes the cache headers of a CMS page, once everything else is done with the
 * response.
 *
 * It has to run last. Whether the answer may be shared depends on the cookies
 * on it, and those are added by the session listener on `kernel.response`,
 * after the page was rendered. Deciding earlier means deciding on a response
 * that is not finished.
 */
final readonly class PageCacheListener
{
    /** Set by whatever rendered a page, and read here. */
    public const string TAGS_ATTRIBUTE = '_cms_cache_tags';

    public function __construct(
        private PageCachePolicy $policy,
        private ?CoreSharedCache $sharedCache = null,
    ) {
    }

    /**
     * Below the -1000 of Symfony's session listener, so the cookies it adds are
     * already on the response.
     */
    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -1024)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $tags = $request->attributes->get(self::TAGS_ATTRIBUTE);

        // The shared cache of the core has the last word once the shop turns it on.
        // Every page may show the menus and the site settings of the module (a theme
        // hook, a footer menu), so every page is filed under them, and a page of the
        // module under its own tags as well.
        if (null !== $this->sharedCache && $this->sharedCache->isActive()) {
            $this->sharedCache->tag([CacheTags::SITE, CacheTags::MENUS, ...(\is_array($tags) ? array_values($tags) : [])]);

            return;
        }

        if (!\is_array($tags) || [] === $tags) {
            return;
        }

        $this->policy->apply($event->getResponse(), $request, array_values($tags));
    }
}
