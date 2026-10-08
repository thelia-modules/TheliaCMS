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

namespace TheliaCMS\Tests\Integration\Settings;

use Thelia\Core\Security\AccessManager;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Model\CmsPageTypeDefinition;
use TheliaCMS\Model\CmsPageTypeDefinitionQuery;
use TheliaCMS\Model\Map\CmsPageTypeDefinitionTableMap;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * CMS > Settings > Page types, requested over HTTP by an administrator: the
 * list and its template indicator, adding a type, and the deletions the screen
 * refuses.
 */
final class PageTypeScreenTest extends AdminScreenTestCase
{
    private const string SCREEN = '/admin/cms/settings/page-types';

    public function testTheScreenListsTheTypesAndTheTemplateEachOneGets(): void
    {
        $screen = $this->screen(self::SCREEN);

        foreach (['default', 'full-width', 'landing'] as $code) {
            self::assertSame(1, $screen->filter(\sprintf('[data-testid="cms-page-type-row-%s"]', $code))->count(), $code.' is listed.');
        }

        $source = (string) $screen->filter('[data-testid="cms-page-type-template-landing"]')->attr('data-source');
        self::assertContains($source, ['theme', 'module', 'theme-fallback', 'module-fallback']);
        self::assertSame(0, $screen->filter('[data-testid="cms-page-type-delete-default"]')->count(), 'The default type offers no deletion.');
    }

    public function testAValidCodeIsAdded(): void
    {
        self::assertSame(302, $this->submitCode('recipe'));

        self::assertTrue($this->typeExists('recipe'));
    }

    public function testACodeThatIsNotOneIsRefused(): void
    {
        self::assertSame(200, $this->submitCode('Recette du jour'));

        self::assertFalse($this->typeExists('Recette du jour'));
        self::assertGreaterThan(0, $this->client->getCrawler()->filter('.invalid-feedback, .form-error-message')->count(), 'The refusal is shown on the field.');
    }

    public function testATypeTheSiteHasIsNotAddedTwice(): void
    {
        $this->givenType('recipe');

        self::assertSame(200, $this->submitCode('recipe'));

        self::assertSame(1, CmsPageTypeDefinitionQuery::create()->filterByCode('recipe')->count());
    }

    public function testATypeAPageHasIsKept(): void
    {
        $this->givenType('recipe');
        $page = $this->createPage('Gratin de pâtes');
        $page->setPageType('recipe')->save();

        $screen = $this->screen(self::SCREEN);
        $action = self::SCREEN.'/recipe/delete';

        self::assertSame(302, $this->send('POST', $action, ['_token' => $this->tokenOfTheForm($screen, $action)]));

        self::assertTrue($this->typeExists('recipe'));
        self::assertStringContainsString('recipe', $this->client->followRedirect()->filter('[role="alert"]')->text(''));
    }

    public function testATypeNoPageHasIsDeleted(): void
    {
        $this->givenType('recipe');

        $screen = $this->screen(self::SCREEN);
        $action = self::SCREEN.'/recipe/delete';

        self::assertSame(302, $this->send('POST', $action, ['_token' => $this->tokenOfTheForm($screen, $action)]));

        self::assertFalse($this->typeExists('recipe'));
    }

    /**
     * `cms_page_type.code` ignores case and trailing spaces: without the route
     * requirement, `DEFAULT` would reach the row of `default`.
     */
    public function testTheDefaultTypeCannotBeDeletedUnderAnySpelling(): void
    {
        $token = $this->tokenOfTheScripts($this->screen(self::SCREEN));

        foreach (['default', 'DEFAULT', 'Default', 'default%20'] as $spelling) {
            self::assertSame(404, $this->send('POST', self::SCREEN.'/'.$spelling.'/delete', [], [...self::XHR, 'HTTP_X_CSRF_TOKEN' => $token]), $spelling);
        }

        self::assertTrue($this->typeExists('default'));
    }

    public function testSeeingTheTypesIsNotTheRightToChangeThem(): void
    {
        $this->givenType('recipe');
        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::SETTINGS => [AccessManager::VIEW],
        ]));

        $token = $this->tokenOfTheScripts($this->screen(self::SCREEN));
        $server = [...self::XHR, 'HTTP_X_CSRF_TOKEN' => $token];

        self::assertSame(403, $this->send('POST', self::SCREEN, ['page_type_create' => ['code' => 'news']], $server));
        self::assertSame(403, $this->send('POST', self::SCREEN.'/recipe/delete', [], $server));

        self::assertFalse($this->typeExists('news'));
        self::assertTrue($this->typeExists('recipe'));
    }

    private function submitCode(string $code): int
    {
        $form = $this->screen(self::SCREEN)->filter('[data-testid="cms-page-type-add"]')->form();
        $form['page_type_create[code]'] = $code;

        return $this->send('POST', $form->getUri(), $form->getPhpValues());
    }

    private function givenType(string $code): void
    {
        (new CmsPageTypeDefinition())->setCode($code)->save();
    }

    /**
     * Compared byte for byte: the column ignores case, and the test has to
     * tell `default` from `DEFAULT`.
     */
    private function typeExists(string $code): bool
    {
        CmsPageTypeDefinitionTableMap::clearInstancePool();

        $statement = $this->getPropelConnection()->prepare('SELECT COUNT(*) FROM cms_page_type WHERE BINARY code = ?');
        $statement->execute([$code]);

        return 0 < (int) $statement->fetchColumn();
    }
}
