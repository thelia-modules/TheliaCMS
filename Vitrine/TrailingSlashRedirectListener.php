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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;

/**
 * Sends an address that differs from a real one only by its trailing slash to
 * the form without it, with a 301.
 *
 * Symfony does this for its own routes. The rewriting router does not, so
 * `/mentions-legales/` answers 404 while `/mentions-legales` answers 200. Every
 * site moved over from WordPress, Drupal or Prestashop arrives with the slash on
 * every indexed address and every inbound link: on the site this was written
 * for, 364 of 392 addresses answered 404 for that reason alone.
 *
 * Runs *above* the listener serving the 404 page of the site (132) and above the
 * core listener rendering the 404 of the theme (128), because
 * `ExceptionEvent::setResponse()` stops propagation: a redirectable address would
 * otherwise be handed the 404 page before anybody looked at its slash.
 *
 * Costs nothing on a site that does not need it. It is only ever called on a
 * 404, and an address without a trailing slash leaves on two string tests,
 * before any query.
 */
final readonly class TrailingSlashRedirectListener
{
    /**
     * First segments this module never answers for. Compared as segments and not
     * as prefixes, so a page addressed `/administration-des-ventes` is not
     * mistaken for the back office.
     *
     * @var list<string>
     */
    private const array RESERVED_SEGMENTS = ['admin', 'api'];

    public function __construct(
        private RewrittenAddressReachability $addresses,
    ) {
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 136)]
    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getThrowable() instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();

        // A browser turns a redirected POST into a GET and drops the body, so a
        // form posted to the wrong address is better off with the 404 it asked
        // for.
        if (!$request->isMethodSafe()) {
            return;
        }

        $path = $this->pathOf($request);

        if ('/' === $path || !str_ends_with($path, '/')) {
            return;
        }

        // Several trailing slashes collapse in one hop rather than one per
        // slash.
        $canonical = rtrim($path, '/');

        if ('' === $canonical || $this->isReserved($canonical)) {
            return;
        }

        if (!$this->addresses->answers($canonical)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->target($request, $canonical), Response::HTTP_MOVED_PERMANENTLY));
    }

    /**
     * The path as it was received.
     *
     * `getPathInfo()` of the Thelia request drops a trailing slash of its own
     * when `allow_slash_ended_uri` is on in the store configuration, so reading
     * it here would show an address that already looks canonical and nothing
     * would ever be redirected. The rewriting router resolves the real one,
     * which is the address that answered 404.
     */
    private function pathOf(Request $request): string
    {
        return $request instanceof TheliaRequest ? $request->getRealPathInfo() : $request->getPathInfo();
    }

    /**
     * The address to send the visitor to, built from the request.
     *
     * The host comes from what was received rather than from the configured
     * default URI, and the query string travels: a campaign parameter dropped by
     * a redirection is a visit attributed to nobody.
     *
     * The query string is passed on exactly as it arrived. `getQueryString()`
     * sorts the parameters, which is harmless for a campaign tag and fatal for
     * anything signed over the string it was sent as.
     *
     * The result never ends on a slash, so the request it points at cannot come
     * back here: one hop, whatever the address.
     */
    private function target(Request $request, string $canonical): string
    {
        $query = (string) $request->server->get('QUERY_STRING', '');

        return $request->getSchemeAndHttpHost()
            .$request->getBaseUrl()
            .$canonical
            .('' === $query ? '' : '?'.$query);
    }

    private function isReserved(string $path): bool
    {
        $first = explode('/', trim($path, '/'))[0];

        return \in_array($first, self::RESERVED_SEGMENTS, true);
    }
}
