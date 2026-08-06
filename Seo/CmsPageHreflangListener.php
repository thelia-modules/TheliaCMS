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

namespace TheliaCMS\Seo;

use SEOne\Event\AlternateHreflangEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;
use TheliaCMS\Page\PublishedPageRepository;
use TheliaCMS\TheliaCMS;

/**
 * Points the hreflang alternates of a CMS page at the page's own slug in each
 * language.
 *
 * SEOne's listener knows the core views and falls back to appending
 * `?lang=xx` to the current URI for anything else, unconditionally — a French
 * alternate would advertise `/our-services?lang=fr_FR` instead of
 * `/nos-services`. It sets the URL at priority 128 with no guard, so the
 * correction has to run *after* it: a lower priority, overwriting the value.
 */
final readonly class CmsPageHreflangListener
{
    public function __construct(
        private PublishedPageRepository $pages,
    ) {
    }

    #[AsEventListener(event: AlternateHreflangEvent::BASE_EVENT_NAME, priority: 64)]
    public function onAlternateHreflang(AlternateHreflangEvent $event): void
    {
        $request = $event->getRequest();

        if (TheliaCMS::PAGE_VIEW !== $request->attributes->get('_view')) {
            return;
        }

        $pageId = $request->query->getInt(TheliaCMS::PAGE_VIEW.'_id');
        $locale = $event->getLang()->getLocale();

        // Only languages the page is actually published in belong in hreflang;
        // advertising a 404 to search engines is worse than saying nothing.
        if ($pageId <= 0 || null === $this->pages->find($pageId, $locale)) {
            return;
        }

        // The home page lives at the site root, not at its slug.
        if ($pageId === (int) TheliaCMS::getConfigValue('home_page_id', 0)) {
            $event->setUrl(rtrim($this->baseUrl($event), '/').'/');

            return;
        }

        $retriever = URL::getInstance()?->retrieve(TheliaCMS::PAGE_VIEW, $pageId, $locale);
        $path = $retriever?->rewrittenUrl ?: $retriever?->url;

        if (null === $path || '' === $path) {
            return;
        }

        $event->setUrl(rtrim($this->baseUrl($event), '/').'/'.ltrim((string) parse_url($path, \PHP_URL_PATH), '/'));
    }

    private function baseUrl(AlternateHreflangEvent $event): string
    {
        if (ConfigQuery::isMultiDomainActivated()) {
            return (string) $event->getLang()->getUrl();
        }

        $configured = ConfigQuery::getConfiguredShopUrl();

        return '' === (string) $configured ? $event->getRequest()->getSchemeAndHttpHost() : (string) $configured;
    }
}
