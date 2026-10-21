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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RequestContext;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
 * A request to the shop for work run from the command line.
 *
 * A component of the theme reads its records through the API of Thelia,
 * whose sub-requests are built from the main request: from a command there
 * is none, and every page holding such a component failed to render with
 * "No current request found". The work runs with a request to the address of
 * the shop on the stack, taken off afterwards; under a web request, the work
 * runs as it is.
 *
 * The URL tool of Thelia is a singleton the HTTP kernel creates at each
 * request: asking for it here creates it for the command, whose links would
 * otherwise fail with "URL instance is not initialized". The request carries
 * a session kept in memory, which the images and the prices of a card read,
 * and the context of the router is the one of that request meanwhile: the
 * addresses of the images are built from it, not from the stack.
 */
final readonly class ConsoleRequest
{
    public function __construct(
        private RequestStack $requests,
        private RequestContext $context,
        // Taken for its creation alone, which sets the singleton.
        // @phpstan-ignore property.onlyWritten
        private URL $url,
    ) {
    }

    /**
     * @template T
     *
     * @param \Closure(): T $work
     * @param string|null   $url  the address of the shop, over the one it is configured with
     *
     * @return T
     */
    public function around(\Closure $work, ?string $url = null): mixed
    {
        if (null !== $this->requests->getMainRequest()) {
            return $work();
        }

        $url = '' !== (string) $url ? (string) $url : (string) ConfigQuery::getConfiguredShopUrl();

        // Without an address, no request is made up: the links of a page
        // would point at a host that is not the shop's.
        if ('' === $url) {
            try {
                return $work();
            } catch (\RuntimeException $exception) {
                if ('No current request found' !== $exception->getMessage()) {
                    throw $exception;
                }

                throw new \RuntimeException('This page renders a component of the theme, whose links need the address of the shop: set url_site in the configuration, or pass --url.', 0, $exception);
            }
        }

        $request = Request::create($url);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requests->push($request);
        $previous = clone $this->context;
        $this->context->fromRequest($request);

        try {
            return $work();
        } finally {
            $this->requests->pop();
            $this->context
                ->setBaseUrl($previous->getBaseUrl())
                ->setPathInfo($previous->getPathInfo())
                ->setMethod($previous->getMethod())
                ->setHost($previous->getHost())
                ->setScheme($previous->getScheme())
                ->setHttpPort($previous->getHttpPort())
                ->setHttpsPort($previous->getHttpsPort())
                ->setQueryString($previous->getQueryString());
        }
    }
}
