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
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsBlockQuery;
use TheliaCMS\Model\Map\CmsBlockTableMap;
use TheliaCMS\Security\CmsResources;

/**
 * Reading a screen is not the right to save it: an editor allowed to look at
 * the CMS pages, and nothing more, cannot change a block.
 */
final class WritePermissionTest extends AdminScreenTestCase
{
    public function testABlockIsSavedOnlyWithTheRightToChangeThePages(): void
    {
        $block = (new CmsBlock())->setCode('permission-block');
        $block->setLocale($this->locale())->setTitle('Before');
        $block->save();
        $url = \sprintf('/admin/cms/blocks/%d', $block->getId());

        $this->logInAs((new FixtureFactory($this->getPropelConnection()))->restrictedAdmin([
            CmsResources::PAGE => [AccessManager::VIEW],
        ]));

        $form = $this->screen($url)->filter('form[name="cms_block"]')->form();
        $form['cms_block[title]'] = 'After';

        self::assertSame(403, $this->send('POST', $form->getUri(), $form->getPhpValues()));

        CmsBlockTableMap::clearInstancePool();
        $saved = CmsBlockQuery::create()->findPk($block->getId());
        self::assertNotNull($saved);
        self::assertSame('Before', $saved->setLocale($this->locale())->getTitle());
    }
}
