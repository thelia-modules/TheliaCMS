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

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\Lang;
use TheliaCMS\Page\CmsPageRenderer;
use TheliaCMS\Page\PublishedPageRepository;
use TheliaCMS\Settings\CmsSettings;

/**
 * Serves a CMS page when an address does not exist, so the 404 of a site is
 * written by the person who writes the site.
 *
 * Runs *above* the core listener that renders the theme's 404, because
 * `ExceptionEvent::setResponse()` stops propagation: whichever listener answers
 * first is the only one that answers at all. Falling back is therefore done by
 * returning early rather than by letting a later listener win.
 *
 * The page is served with the 404 status: a soft 404 answering 200 is worse than
 * no custom page at all.
 */
final readonly class NotFoundPageListener
{
    public function __construct(
        private CmsSettings $settings,
        private PublishedPageRepository $pages,
        private CmsPageRenderer $renderer,
        private LangService $langService,
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 132)]
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();

        // The back office and the API have their own answers, and neither wants
        // a themed page.
        if (str_starts_with($request->getPathInfo(), '/admin') || str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $pageId = $this->settings->notFoundPageId();

        if (null === $pageId) {
            return;
        }

        $locale = $this->langService->getLang()?->getLocale() ?? Lang::getDefaultLanguage()->getLocale();
        $page = $this->pages->find($pageId, $locale);

        // Not published in this language: the theme's 404 is the honest
        // fallback, rather than a page in the wrong language.

        if (null === $page) {
            return;
        }

        try {
            $body = $this->renderer->render($page);
        } catch (\Throwable $throwable) {
            // A 404 that turns into a 500 loses both the page and the status, so
            // the theme keeps the hand — but silently swallowing the reason is
            // how a broken 404 page stays broken.
            $this->logger->warning('The CMS page configured for missing addresses could not be rendered: {reason}', [
                'reason' => $throwable->getMessage(),
                'page' => $pageId,
                'exception' => $throwable,
            ]);

            return;
        }

        $response = new Response($body, Response::HTTP_NOT_FOUND);
        $response->headers->set('X-Robots-Tag', 'noindex');

        $event->setResponse($response);
    }
}
