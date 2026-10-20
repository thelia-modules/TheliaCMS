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

namespace TheliaCMS\Tests\Integration\Security;

use Thelia\Core\Security\AccessManager;
use Thelia\Test\FixtureFactory;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\Map\CmsPageTableMap;
use TheliaCMS\Security\CmsResources;

/**
 * Reading a screen is not the right to save it: an editor allowed to look at
 * the CMS pages, and nothing more, cannot change a page.
 */
final class WritePermissionTest extends AdminScreenTestCase
{
    public function testAPageIsSavedOnlyWithTheRightToChangeThePages(): void
    {
        $page = $this->createPage('Before', published: false);
        $url = \sprintf('/admin/cms/pages/%d', $page->getId());

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW],
        ]));

        $form = $this->screen($url)->filter('form[name="cms_page"]')->form();
        $form['cms_page[title]'] = 'After';

        self::assertSame(403, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        CmsPageTableMap::clearInstancePool();
        $saved = CmsPageQuery::create()->findPk($page->getId());
        self::assertNotNull($saved);
        self::assertSame('Before', $saved->setLocale($this->locale())->getTitle());
    }

    public function testTheGeneratorIsRefusedToWhoeverMayOnlyChangePages(): void
    {
        $page = $this->createPage('Generate permission', published: false);

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW, AccessManager::UPDATE],
        ]));

        $screen = $this->screen(\sprintf('/admin/cms/pages/%d/content', $page->getId()));
        $token = $this->tokenOfTheScripts($screen);

        foreach (['generate' => ['cms_brief' => ['text' => 'A page.']], 'revise' => ['cms_change' => ['text' => 'Shorter.']], 'section' => ['cms_change_content' => ['section' => 'content', 'text' => 'Shorter.']]] as $action => $fields) {
            self::assertSame(403, $this->send('POST', \sprintf('/admin/cms/pages/%d/content/%s', $page->getId(), $action), ['_token' => $token, ...$fields]), $action.' is refused without the generate right.');
        }

        self::assertSame(0, \TheliaCMS\Model\CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->count(), 'Nothing was asked of the generator.');
    }
}
