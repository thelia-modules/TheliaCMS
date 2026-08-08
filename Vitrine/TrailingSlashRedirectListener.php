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
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Thelia\Core\HttpFoundation\Request as TheliaRequest;

/**
 * Sends an address that differs from a real one only by its trailing slash to
 * the form without it, with a 301.
 *
 * Symfony does this for its own routes. The rewriting router does not, so
 * `/mentions-legales/` answers 404 while `/mentions-legales` answers 200. Every
 * site moved over from WordPress, Drupal or Prestashop arrives with the slash on
 * every indexed address and every inbound link.
 *
 * Runs on the request and not on the resulting 404, above the router (32).
 * Waiting for the 404 misses the addresses another route answers for: with
 * `allow_slash_ended_uri` on, the Thelia request hides the trailing slash from
 * the Symfony routes, so `/contact/` falls through the rewriting router into the
 * `/{_view}` catch-all of the Front module and renders the contact form of the
 * theme, with a 200. The page of this module answering on `/contact` was never
 * reached and nothing said so. Every single segment address whose name matches a
 * template of the theme is in that state, which on a WordPress takeover means
 * `/contact/`, `/faq/`, `/login/` and `/sitemap/`.
 *
 * Costs nothing on a site that does not need it: an address without a trailing
 * slash leaves on two string tests, before any query.
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

    /**
     * Priority 34: above the router (32), which is what makes this happen before
     * any route claims the address, and below the maintenance page (40) and the
     * asset server (35), which both answer for themselves.
     */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 34)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
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

        // An address the rewriting table holds as it was received is the site
        // saying that form is its own: the router serves it, or answers the 301
        // of a rename on it. Stepping in first would add a hop to every old
        // address a takeover kept in both forms.
        if ($this->addresses->isKnown($path)) {
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
