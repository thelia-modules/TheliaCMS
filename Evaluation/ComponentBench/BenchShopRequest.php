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

namespace TheliaCMS\Evaluation\ComponentBench;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;

/**
 * A visit of the shop for the bench, which runs on the command line: the
 * components of the theme read the catalogue through the API layer of the
 * core (`resources()`), which builds its requests from the current one, its
 * session and the URL service. In the back office the generator runs inside
 * the request of the editor; here the bench lends it one.
 */
final readonly class BenchShopRequest
{
    public function __construct(
        private RequestStack $requests,
        // Built here, so the URL service of the core exists: the HTTP kernel
        // builds it for a request, the console never does.
        private URL $urls,
    ) {
    }

    /**
     * Pushes a request to the home page of the shop when there is none, and
     * gives back what takes it away again.
     *
     * @return \Closure(): void
     */
    public function open(): \Closure
    {
        if (null !== $this->requests->getCurrentRequest()) {
            return static function (): void {};
        }

        $shop = trim((string) ConfigQuery::getConfiguredShopUrl());
        $request = Request::create(rtrim('' !== $shop ? $shop : $this->urls->getBaseUrl(), '/').'/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requests->push($request);

        return function (): void {
            $this->requests->pop();
        };
    }
}
