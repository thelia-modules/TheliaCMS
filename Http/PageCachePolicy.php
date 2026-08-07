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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use TheliaCMS\TheliaCMS;

/**
 * Decides whether a rendered page may be kept by a shared cache, and says what
 * it contains so the cache can drop it later.
 *
 * No listener in the core sets `Cache-Control` on a front-office page, so
 * without this a proxy either caches nothing or follows its own defaults, which
 * on a site with a cart is how one visitor's session reaches another.
 *
 * The `Cache-Tag` header is written whatever the answer about caching: it costs
 * nothing, and a proxy configured to cache these pages needs the tags to
 * invalidate them.
 */
final readonly class PageCachePolicy
{
    public const string TTL_SETTING = 'http_cache_ttl';

    /** Off by default: a site puts a proxy in front of itself on purpose. */
    public const int DEFAULT_TTL = 0;

    /** A day. Past that the tags matter more than the age. */
    public const int MAX_TTL = 86400;

    public function ttl(): int
    {
        $configured = (int) TheliaCMS::getConfigValue(self::TTL_SETTING, (string) self::DEFAULT_TTL);

        return max(0, min($configured, self::MAX_TTL));
    }

    public function isEnabled(): bool
    {
        return $this->ttl() > 0;
    }

    /**
     * Whether this answer may be handed to somebody else.
     *
     * Three ways to say no. The request is not a plain read. The visitor
     * arrived with a session, so the page may name them. Or the answer sets a
     * cookie, which a shared cache would then serve to the next visitor as
     * their own. Thelia opens a session on every front-office request, so the
     * third one is the usual answer and the proxy has to be told to drop the
     * session cookie on these addresses before anything is cached at all.
     */
    public function isCacheable(Request $request, Response $response): bool
    {
        if (!$request->isMethodCacheable() || Response::HTTP_OK !== $response->getStatusCode()) {
            return false;
        }

        if ($request->hasPreviousSession()) {
            return false;
        }

        return [] === $response->headers->getCookies();
    }

    /**
     * @param list<string> $tags
     */
    public function apply(Response $response, Request $request, array $tags): void
    {
        // Two names for one list: Fastly and Cloudflare read `Surrogate-Key`,
        // Varnish and the rest read `Cache-Tag`. Both cost a header and save an
        // adapter per CDN.
        $response->headers->set('Cache-Tag', implode(',', $tags));
        $response->headers->set('Surrogate-Key', implode(' ', $tags));

        // Cheapest check first, and the one that does not need the database:
        // most answers are ruled out here whatever the site is configured to
        // do.
        if (!$this->isCacheable($request, $response)) {
            $response->headers->set('Cache-Control', 'private, no-store');

            return;
        }

        $ttl = $this->ttl();

        if ($ttl <= 0) {
            $response->headers->set('Cache-Control', 'private, no-store');

            return;
        }

        $response->setPublic();
        $response->setMaxAge(0);
        $response->setSharedMaxAge($ttl);

        // The same address answers in the language the visitor asked for, so a
        // shared cache has to key on it.
        $response->setVary(['Accept-Language'], false);

        // Symfony rewrites Cache-Control to `private` whenever the session was
        // touched, which on Thelia is always. This header is its documented way
        // of being told the answer was checked and may be shared; Symfony
        // removes it before the response goes out.
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');
    }
}
