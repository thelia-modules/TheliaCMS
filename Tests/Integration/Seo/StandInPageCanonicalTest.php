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

namespace TheliaCMS\Tests\Integration\Seo;

use SEOne\Event\SEOneUrlEvent;
use SEOne\Event\SEOneUrlEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * The canonical URL of a response that serves a page other than the one its
 * address designates — the page shown on an address that does not exist.
 *
 * Skipped on a shop without SEOne: the canonical is then whatever the theme
 * writes, and the module has nothing to correct.
 */
final class StandInPageCanonicalTest extends CmsIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(SEOneUrlEvent::class)) {
            self::markTestSkipped('SEOne is not installed on this shop.');
        }
    }

    public function testItNamesThePageActuallyRendered(): void
    {
        $notFoundPage = $this->createPage('Page introuvable');

        $canonical = $this->canonicalFor($this->standInRequestFor((int) $notFoundPage->getId()));

        self::assertStringEndsWith(
            '/page-introuvable',
            $canonical,
            'The canonical names the address asked for, while the title and the breadcrumb name the page served.',
        );
    }

    public function testAnOrdinaryPageIsLeftToSeoOne(): void
    {
        $page = $this->createPage('Nos services');

        $request = Request::create('/nos-services?'.TheliaCMS::PAGE_VIEW.'_id='.$page->getId());
        $request->attributes->set('_view', TheliaCMS::PAGE_VIEW);

        // No marker: the address and the page agree, and a canonical override
        // typed in the back office has to keep winning.
        self::assertStringEndsWith('/nos-services', $this->canonicalFor($request));
    }

    /**
     * A request as the 404 listener hands it over: the address of a page that no
     * longer answers, renamed to the page being served.
     */
    private function standInRequestFor(int $servedPageId): Request
    {
        $request = Request::create('/une-adresse-qui-nexiste-pas?'.TheliaCMS::PAGE_VIEW.'_id='.$servedPageId);
        $request->attributes->set('_view', TheliaCMS::PAGE_VIEW);
        $request->attributes->set(TheliaCMS::PAGE_VIEW.'_id', $servedPageId);
        $request->attributes->set(TheliaCMS::STAND_IN_PAGE_ATTRIBUTE, $servedPageId);

        return $request;
    }

    /**
     * The request under test has to *replace* the one the test case pushed, not
     * sit on top of it: both this listener and the services it reads ask the
     * stack for the main request, which stays the first one pushed. Stacking
     * would leave them describing `http://localhost/`.
     *
     * It also borrows that request's session, which SEOne reads to know the
     * language before it does anything else.
     */
    private function canonicalFor(Request $request): string
    {
        $stack = $this->getService(RequestStack::class);
        $pushedByTheTestCase = $stack->pop();

        if ($pushedByTheTestCase?->hasSession()) {
            $request->setSession($pushedByTheTestCase->getSession());
        }

        $stack->push($request);

        try {
            $event = new SEOneUrlEvent();
            $this->getService(EventDispatcherInterface::class)->dispatch($event, SEOneUrlEvents::GENERATE_CANONICAL);

            return (string) $event->getUrl();
        } finally {
            $stack->pop();

            if (null !== $pushedByTheTestCase) {
                $stack->push($pushedByTheTestCase);
            }
        }
    }
}
