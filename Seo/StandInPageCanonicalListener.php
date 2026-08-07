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

use SEOne\Event\SEOneUrlEvent;
use SEOne\Event\SEOneUrlEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Lang;
use TheliaCMS\Locale\AlternateUrls;
use TheliaCMS\TheliaCMS;

/**
 * Points the canonical URL at the page a response actually renders, when that
 * is not the page its address designates.
 *
 * The page shown on an address that does not exist is the case that occurs: the
 * title, the breadcrumb and its BreadcrumbList already name the page served,
 * while the canonical — and the `og:url` built from it — still named the
 * address asked for. The response carries `X-Robots-Tag: noindex`, so nothing
 * indexes either of them; what is fixed here is a response describing itself as
 * two different things.
 *
 * Runs above the SEOne listener, which leaves an URL already set alone but
 * otherwise builds one from the path of the current request. Only ever answers
 * for a stand-in response: on an ordinary CMS page the address and the page
 * agree, and the canonical override an editor may have typed in the back office
 * has to keep winning.
 */
final readonly class StandInPageCanonicalListener
{
    public function __construct(
        private RequestStack $requestStack,
        private AlternateUrls $alternates,
        private LangService $langService,
    ) {
    }

    #[AsEventListener(event: SEOneUrlEvents::GENERATE_CANONICAL, priority: 192)]
    public function onGenerateCanonical(SEOneUrlEvent $event): void
    {
        // The contract of this event is that the first listener to answer wins,
        // and this one is no exception: it runs early rather than loudly.
        if (null !== $event->getUrl()) {
            return;
        }

        $request = $this->requestStack->getMainRequest();

        if (null === $request || null === $request->attributes->get(TheliaCMS::STAND_IN_PAGE_ATTRIBUTE)) {
            return;
        }

        $locale = $this->langService->getLang()?->getLocale() ?? Lang::getDefaultLanguage()->getLocale();
        $url = $this->alternates->forLocale($locale);

        // No address in this language: nothing truer to say than what SEOne
        // would have said on its own.
        if (null === $url || '' === $url) {
            return;
        }

        $event->setUrl($url);
    }
}
