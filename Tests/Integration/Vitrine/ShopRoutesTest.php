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

namespace TheliaCMS\Tests\Integration\Vitrine;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\SiteMode;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;
use TheliaCMS\Vitrine\ShopRoutesListener;

/**
 * A showcase site answers 404 on its cart and its checkout, under every
 * spelling the router reads as those addresses.
 */
final class ShopRoutesTest extends CmsIntegrationTestCase
{
    private ?string $previousMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousMode = TheliaCMS::getConfigValue(CmsSettings::SITE_MODE);
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, SiteMode::Showcase->value);
    }

    protected function tearDown(): void
    {
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, $this->previousMode ?? SiteMode::Commerce->value);

        parent::tearDown();
    }

    public function testTheCartIsClosedUnderAnEncodedAddress(): void
    {
        foreach (['/cart', '/c%61rt', '/%6Frder/delivery', '/checkout/'] as $path) {
            try {
                $this->listener()->onKernelRequest($this->eventFor($path));
                self::fail(\sprintf('%s is left open on a showcase site.', $path));
            } catch (NotFoundHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testThePagesStayOpen(): void
    {
        $this->listener()->onKernelRequest($this->eventFor('/cartes-de-visite'));

        $this->addToAssertionCount(1);
    }

    private function listener(): ShopRoutesListener
    {
        return new ShopRoutesListener($this->getService(CmsSettings::class));
    }

    private function eventFor(string $path): RequestEvent
    {
        return new RequestEvent(static::$kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST);
    }
}
