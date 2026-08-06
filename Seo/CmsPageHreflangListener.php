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
use TheliaCMS\Locale\AlternateUrls;
use TheliaCMS\TheliaCMS;

/**
 * Points the hreflang tags of a CMS page at the page itself in each language.
 *
 * Runs below the SEOne listener, which sets the alternate without checking
 * whether anything else already did.
 *
 * The addresses come from the same service as the language switcher: hreflang
 * and the switcher disagreeing on where a translation lives is how a site ends
 * up advertising URLs no visitor is ever sent to.
 */
final readonly class CmsPageHreflangListener
{
    public function __construct(
        private AlternateUrls $alternates,
    ) {
    }

    #[AsEventListener(event: AlternateHreflangEvent::BASE_EVENT_NAME, priority: 64)]
    public function onAlternateHreflang(AlternateHreflangEvent $event): void
    {
        if (TheliaCMS::PAGE_VIEW !== $event->getRequest()->attributes->get('_view')) {
            return;
        }

        // No version in that language: the tag has to be dropped, not left
        // alone. SEOne fills it in first, from the current URL and a `lang`
        // parameter, which would advertise a page answering 404 — and an empty
        // address is how SEOne is told to skip a language.
        $event->setUrl($this->alternates->forLocale($event->getLang()->getLocale()) ?? '');
    }
}
