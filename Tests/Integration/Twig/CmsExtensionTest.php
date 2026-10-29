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
use Twig\Environment;
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
     * A theme marks the home page in its navigation: it passes the id of a page
     * as it holds it, a number.
     */
    public function testThePageSetAsHomeIsReportedAndNoOtherIs(): void
    {
        $previous = TheliaCMS::getConfigValue('home_page_id');
        $home = $this->createPage('Accueil du test');
        $other = $this->createPage('Autre page du test');
        TheliaCMS::setConfigValue('home_page_id', (string) $home->getId());

        try {
            self::assertSame('home', $this->render('{{ cms_is_home(id) ? "home" : "not" }}', ['id' => (int) $home->getId()]));
            self::assertSame('not', $this->render('{{ cms_is_home(id) ? "home" : "not" }}', ['id' => (int) $other->getId()]));
            self::assertTrue($this->call('cms_is_home', (string) $home->getId()));
        } finally {
            TheliaCMS::setConfigValue('home_page_id', $previous);
        }
    }

    /**
     * A site with no home page has none: not the page of id 0, nor a page whose
     * id the theme does not have (a product page passes nothing).
     */
    public function testWithoutAHomePageNoIdIsTheHomePage(): void
    {
        $previous = TheliaCMS::getConfigValue('home_page_id');
        TheliaCMS::setConfigValue('home_page_id', '');

        try {
            self::assertFalse($this->call('cms_is_home', '0'));
            self::assertFalse($this->call('cms_is_home', ''));
            self::assertSame('not', $this->render('{{ cms_is_home(id) ? "home" : "not" }}', ['id' => null]));
        } finally {
            TheliaCMS::setConfigValue('home_page_id', $previous);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $source, array $context): string
    {
        $twig = $this->getService(Environment::class);
        \assert($twig instanceof Environment);

        return $twig->createTemplate($source)->render($context);
    }

    /**
     * Through the registered function and not through the service behind it: what
     * a theme writes is the name, and a function renamed or dropped takes every
     * template using it down at compile time.
     */
    private function call(string $name, mixed ...$arguments): mixed
    {
        foreach ($this->getService(CmsExtension::class)->getFunctions() as $function) {
            \assert($function instanceof TwigFunction);

            if ($name === $function->getName()) {
                return ($function->getCallable())(...$arguments);
            }
        }

        self::fail(\sprintf('The extension registers no "%s" function, so no theme can ask.', $name));
    }
}
