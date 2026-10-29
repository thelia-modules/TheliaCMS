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

namespace TheliaCMS\Tests\Integration\Page;

use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Model\Map\CmsPageTableMap;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\Admin\ModelPageSettingException;
use TheliaCMS\Page\PageTypeWriter;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The model of a page template is a reference new pages start from: it is
 * never online, and it goes with its template rather than to the bin. Neither
 * setting is offered on its screen, and the writes refuse them when posted.
 */
final class ModelPageSettingsScreenTest extends AdminScreenTestCase
{
    private const string TYPE = 'model-settings-test';

    public function testTheWriterRefusesToBinOrShowAModel(): void
    {
        $model = $this->givenTheModel();

        try {
            $this->writer()->moveToTrash($model);
            self::fail('A model went to the bin.');
        } catch (ModelPageSettingException) {
        }

        try {
            $this->writer()->toggleVisibility($model);
            self::fail('A model was put online.');
        } catch (ModelPageSettingException) {
        }

        self::assertSame(0, $this->reloaded($model)->getVisible());
        self::assertNull($this->reloaded($model)->getDeletedAt());
    }

    public function testPostingToTheBinOrToTheVisibilityOfAModelChangesNothing(): void
    {
        $model = $this->givenTheModel();
        $token = $this->tokenOfTheScripts($this->screen('/admin/cms/pages'));
        $server = [...self::SAME_SITE, 'HTTP_X_CSRF_TOKEN' => $token];

        self::assertSame(302, $this->send('POST', \sprintf('/admin/cms/pages/%d/delete', $model->getId()), [], $server));
        self::assertSame(302, $this->send('POST', \sprintf('/admin/cms/pages/%d/visibility', $model->getId()), [], $server));

        $reloaded = $this->reloaded($model);
        self::assertNull($reloaded->getDeletedAt(), 'The model is not in the bin.');
        self::assertSame(0, $reloaded->getVisible(), 'The model is still offline.');
    }

    /**
     * A model an older version or an import left visible is shown offline, and
     * saving its settings puts it offline: the field is not offered.
     */
    public function testTheScreenOfAModelOffersNoVisibilityAndSavingItPutsItOffline(): void
    {
        $model = $this->givenTheModel();
        $model->setVisible(1)->save();

        $screen = $this->screen(\sprintf('/admin/cms/pages/%d', $model->getId()));
        $radios = $screen->filter('input[name="cms_page[visible]"]');

        self::assertGreaterThan(0, $radios->count());
        self::assertSame($radios->count(), $radios->filter('[disabled]')->count(), 'The visibility of a model cannot be changed.');
        self::assertSame('0', $radios->filter('[checked]')->attr('value'), 'A model is shown offline.');

        $form = $screen->filter('form[name="cms_page"]')->form();

        self::assertSame(302, $this->send('POST', $form->getUri(), $form->getPhpValues()));
        self::assertSame(0, $this->reloaded($model)->getVisible(), 'Saving the model puts it offline.');
    }

    public function testAnOrdinaryPageKeepsItsVisibilitySetting(): void
    {
        $page = $this->createPage('Page ordinaire visible');

        $radios = $this->screen(\sprintf('/admin/cms/pages/%d', $page->getId()))->filter('input[name="cms_page[visible]"]');

        self::assertSame(0, $radios->filter('[disabled]')->count());
    }

    private function givenTheModel(): CmsPage
    {
        $this->getService(PageTypeWriter::class)->add(self::TYPE);

        return $this->writer()->createModel(self::TYPE, $this->locale(), 'Modèle des réglages');
    }

    private function reloaded(CmsPage $page): CmsPage
    {
        CmsPageTableMap::clearInstancePool();
        $reloaded = CmsPageQuery::create()->findPk($page->getId());
        self::assertInstanceOf(CmsPage::class, $reloaded);

        return $reloaded;
    }

    private function writer(): CmsPageWriter
    {
        $writer = $this->getService(CmsPageWriter::class);
        \assert($writer instanceof CmsPageWriter);

        return $writer;
    }
}
