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

use TheliaCMS\Media\CmsMediaLibrary;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockQuery;
use TheliaCMS\Model\CmsForm;
use TheliaCMS\Model\CmsFormQuery;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\Map\CmsBlockTableMap;
use TheliaCMS\Model\Map\CmsFormTableMap;
use TheliaCMS\Model\Map\CmsMenuTableMap;
use TheliaCMS\Model\Map\CmsPageContentTableMap;
use TheliaCMS\Model\Map\CmsPageTableMap;
use TheliaCMS\Page\Admin\CmsPageAdminRepository;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\Admin\PageStatus;
use TheliaCMS\Settings\CmsSettings;
use TheliaLibrary\Model\LibraryImage;
use TheliaLibrary\Model\LibraryImageQuery;
use TheliaLibrary\Model\Map\LibraryImageTableMap;

/**
 * Every write of the CMS back office takes the token of the back office: one
 * action per screen family, refused without the token, done with the one the
 * screen renders.
 */
final class WriteNeedsTheTokenTest extends AdminScreenTestCase
{
    public function testPages(): void
    {
        $page = $this->createPage('Token page');
        $id = (int) $page->getId();
        $action = \sprintf('/admin/cms/pages/%d/unpublish', $id);

        self::assertSame(403, $this->send('POST', $action));
        self::assertSame(PageStatus::Published, $this->statusOf($id), 'Refused, the page stays online.');

        $token = $this->tokenOfTheForm($this->screen(\sprintf('/admin/cms/pages/%d', $id)), $action);

        self::assertSame(302, $this->send('POST', $action, ['_token' => $token]));
        self::assertNotSame(PageStatus::Published, $this->statusOf($id), 'Done, the page is offline.');
    }

    public function testTrash(): void
    {
        $page = $this->createPage('Token trash');
        $id = (int) $page->getId();
        $this->getService(CmsPageWriter::class)->moveToTrash($page);
        $action = \sprintf('/admin/cms/pages/%d/restore', $id);

        self::assertSame(403, $this->send('POST', $action));
        self::assertNotNull($this->freshPage($id)->getDeletedAt(), 'Refused, the page stays in the bin.');

        $token = $this->tokenOfTheForm($this->screen('/admin/cms/pages/trash'), $action);

        self::assertSame(302, $this->send('POST', $action, ['_token' => $token]));
        self::assertNull($this->freshPage($id)->getDeletedAt());
    }

    public function testMenus(): void
    {
        $menu = (new CmsMenu())->setCode('token-menu');
        $menu->setLocale($this->locale())->setTitle('Token menu');
        $menu->save();
        $id = (int) $menu->getId();
        $action = \sprintf('/admin/cms/menus/%d/delete', $id);

        self::assertSame(403, $this->send('POST', $action));
        self::assertNotNull(CmsMenuQuery::create()->findPk($id), 'Refused, the menu stays.');

        $token = $this->tokenOfTheForm($this->screen('/admin/cms/menus'), $action);

        self::assertSame(302, $this->send('POST', $action, ['_token' => $token]));
        CmsMenuTableMap::clearInstancePool();
        self::assertNull(CmsMenuQuery::create()->findPk($id));
    }

    public function testBlocks(): void
    {
        $block = (new CmsBlock())->setCode('token-block');
        $block->setLocale($this->locale())->setTitle('Token block');
        $block->save();
        $id = (int) $block->getId();
        $action = \sprintf('/admin/cms/blocks/%d/delete', $id);

        self::assertSame(403, $this->send('POST', $action));
        self::assertNull($this->freshBlock($id)->getDeletedAt(), 'Refused, the block stays.');

        $token = $this->tokenOfTheForm($this->screen(\sprintf('/admin/cms/blocks/%d', $id)), $action);

        self::assertSame(302, $this->send('POST', $action, ['_token' => $token]));
        self::assertNotNull($this->freshBlock($id)->getDeletedAt());
    }

    public function testForms(): void
    {
        $form = (new CmsForm())->setCode('token-form');
        $form->setLocale($this->locale())->setTitle('Token form');
        $form->save();
        $id = (int) $form->getId();
        $action = \sprintf('/admin/cms/forms/%d/delete', $id);

        self::assertSame(403, $this->send('POST', $action));
        self::assertNull($this->freshForm($id)->getDeletedAt(), 'Refused, the form stays.');

        // The deletion is a button of the settings form, posting it elsewhere.
        $token = $this->tokenOfTheForm($this->screen(\sprintf('/admin/cms/forms/%d', $id)), \sprintf('/admin/cms/forms/%d', $id));

        self::assertSame(302, $this->send('POST', $action, ['_token' => $token]));
        self::assertNotNull($this->freshForm($id)->getDeletedAt());
    }

    public function testMedia(): void
    {
        $image = new LibraryImage();
        $image->setLocale($this->locale())->setFileName('token-media.jpg')->setTitle('Token media');
        $image->save();
        $this->getService(CmsMediaLibrary::class)->tag($image);
        $id = (int) $image->getId();
        $action = \sprintf('/admin/cms/media/%d', $id);

        // The image library of the editor deletes with a script.
        self::assertSame(403, $this->send('DELETE', $action, [], self::XHR));
        self::assertNotNull(LibraryImageQuery::create()->findPk($id), 'Refused, the image stays.');

        $token = $this->tokenOfTheScripts($this->screen('/admin/cms/media'));

        self::assertSame(204, $this->send('DELETE', $action, [], [...self::XHR, 'HTTP_X_CSRF_TOKEN' => $token]));
        LibraryImageTableMap::clearInstancePool();
        self::assertNull(LibraryImageQuery::create()->findPk($id));
    }

    public function testSettings(): void
    {
        $settings = $this->getService(CmsSettings::class);
        $before = $settings->trashRetentionDays();
        $wanted = $before + 7;

        $form = $this->screen('/admin/cms/settings')->filter('form[name="cms_settings"]')->form();
        $form['cms_settings[trashRetentionDays]'] = (string) $wanted;
        $values = $form->getPhpValues();

        self::assertArrayHasKey('_token', $values, 'The settings form carries the token.');

        $withoutToken = $values;
        unset($withoutToken['_token']);

        self::assertSame(403, $this->send('POST', $form->getUri(), $withoutToken));
        self::assertSame($before, $settings->trashRetentionDays(), 'Refused, the settings stay.');

        self::assertSame(302, $this->send('POST', $form->getUri(), $values));
        self::assertSame($wanted, $settings->trashRetentionDays());
    }

    public function testBuilder(): void
    {
        $page = $this->createPage('Token builder');
        $id = (int) $page->getId();

        $form = $this->screen(\sprintf('/admin/cms/pages/%d/builder', $id))->filter('form.cms-builder__form')->form();
        $values = $form->getPhpValues();
        $values['save'] = 'autosave';

        self::assertArrayHasKey('_token', $values, 'The builder form carries the token.');

        $fields = array_key_first(array_filter($values, 'is_array'));
        $values[$fields]['html'] = '<h1>Token builder</h1><p>Saved in the background.</p>';

        $withoutToken = $values;
        unset($withoutToken['_token']);

        self::assertSame(403, $this->send('POST', $form->getUri(), $withoutToken, self::XHR));
        self::assertStringNotContainsString('Saved in the background', $this->draftHtmlOf($id), 'Refused, the draft stays.');

        self::assertSame(200, $this->send('POST', $form->getUri(), $values, self::XHR));
        self::assertStringContainsString('Saved in the background', $this->draftHtmlOf($id));
    }

    private function statusOf(int $id): PageStatus
    {
        return $this->getService(CmsPageAdminRepository::class)->statusOf($this->freshPage($id), $this->locale());
    }

    private function freshPage(int $id): CmsPage
    {
        CmsPageTableMap::clearInstancePool();
        CmsPageContentTableMap::clearInstancePool();

        $page = CmsPageQuery::create()->findPk($id);
        self::assertNotNull($page);

        return $page;
    }

    private function freshBlock(int $id): CmsBlock
    {
        CmsBlockTableMap::clearInstancePool();

        $block = CmsBlockQuery::create()->findPk($id);
        self::assertNotNull($block);

        return $block;
    }

    private function freshForm(int $id): CmsForm
    {
        CmsFormTableMap::clearInstancePool();

        $form = CmsFormQuery::create()->findPk($id);
        self::assertNotNull($form);

        return $form;
    }

    private function draftHtmlOf(int $id): string
    {
        CmsPageContentTableMap::clearInstancePool();

        return (string) CmsPageContentQuery::create()
            ->filterByPageId($id)
            ->filterByLocale($this->locale())
            ->findOne()
            ?->getDraftHtml();
    }
}
