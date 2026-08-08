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

namespace TheliaCMS\Tests\Integration\Twig;

use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\SiteMode;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;
use TheliaCMS\Twig\CmsExtension;
use Twig\TwigFunction;

/**
 * The template API the module offers a theme.
 *
 * `cms_is_showcase` exists because of a defect the pilot site found: on a
 * showcase site the cart and the checkout answer 404, and the header of the theme
 * linked to them all the same, so every page of the site carried a dead link.
 * A theme cannot read a setting of a module on its own, so this is the only way
 * it can ask.
 */
final class CmsExtensionTest extends CmsIntegrationTestCase
{
    private ?string $previousMode = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousMode = TheliaCMS::getConfigValue(CmsSettings::SITE_MODE);
    }

    protected function tearDown(): void
    {
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, $this->previousMode);

        parent::tearDown();
    }

    public function testShowcaseIsReportedToTheTheme(): void
    {
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, SiteMode::Showcase->value);

        self::assertTrue($this->call('cms_is_showcase'));
    }

    public function testAShopIsNotReportedAsAShowcase(): void
    {
        TheliaCMS::setConfigValue(CmsSettings::SITE_MODE, SiteMode::Commerce->value);

        self::assertFalse($this->call('cms_is_showcase'));
    }

    /**
     * Through the registered function and not through the service behind it: what
     * a theme writes is the name, and a function renamed or dropped takes every
     * template using it down at compile time.
     */
    private function call(string $name): mixed
    {
        foreach ($this->getService(CmsExtension::class)->getFunctions() as $function) {
            \assert($function instanceof TwigFunction);

            if ($name === $function->getName()) {
                return ($function->getCallable())();
            }
        }

        self::fail(\sprintf('The extension registers no "%s" function, so no theme can ask.', $name));
    }
}
