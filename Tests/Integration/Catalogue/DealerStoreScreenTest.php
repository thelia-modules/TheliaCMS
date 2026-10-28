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

namespace TheliaCMS\Tests\Integration\Catalogue;

use Dealer\Model\Dealer;
use Thelia\Model\CountryQuery;
use Thelia\Model\HookQuery;
use TheliaCMS\Catalogue\Admin\ItemContentTabHook;
use TheliaCMS\Catalogue\DealerStores;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The store screen of the Dealer module leads to the content screen of the
 * store, which leads back to it.
 */
final class DealerStoreScreenTest extends AdminScreenTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!DealerStores::available()) {
            self::markTestSkipped('The Dealer module is not active on this shop.');
        }

        // bin/test-prepare activates the modules without creating the hooks
        // they declare: `module:activate Dealer` does.
        if (null === HookQuery::create()->findOneByCode('dealer.additional')) {
            self::markTestSkipped('The dealer.additional hook is not registered on this shop.');
        }
    }

    public function testTheStoreScreenHasTheTabOfTheCms(): void
    {
        $id = $this->store();

        $screen = $this->screen(\sprintf('/admin/module/Dealer/dealer/edit?dealer_id=%d', $id));
        $tab = $screen->filter(\sprintf('#%s [data-testid="cms-item-content-tab"]', ItemContentTabHook::TAB_ID));

        self::assertSame(1, $screen->filter(\sprintf('[data-bs-target="#%s"]', ItemContentTabHook::TAB_ID))->count(), 'The store screen has the tab of the CMS.');
        self::assertSame(1, $tab->count());
        self::assertStringContainsString(
            \sprintf('/admin/cms/catalogue/dealer/%d/content', $id),
            (string) $tab->filter('[data-testid="cms-item-content-edit"]')->first()->attr('href'),
        );
    }

    public function testTheContentScreenOfAStoreLeadsBackToIt(): void
    {
        $id = $this->store();

        $screen = $this->screen(\sprintf('/admin/cms/catalogue/dealer/%d/content', $id));

        self::assertSame(1, $screen->filter('[data-testid="cms-item-content"]')->count());
        self::assertStringContainsString(\sprintf('/admin/module/Dealer/dealer/edit?dealer_id=%d', $id), (string) $screen->filter('[data-testid="cms-content-back"]')->attr('href'));

        $this->client->request('GET', '/admin/cms/catalogue/dealer/987654/content');
        self::assertSame(404, $this->client->getResponse()->getStatusCode(), 'An unknown store has no content screen.');
    }

    private function store(): int
    {
        $store = (new Dealer())
            ->setLocale($this->locale())
            ->setTitle('Magasin de Pessac')
            ->setVisible(1)
            ->setAddress1('1 rue du Marché')
            ->setZipcode('33600')
            ->setCity('Pessac')
            ->setCountryId((int) CountryQuery::create()->findOne()?->getId());
        $store->save($this->getPropelConnection());

        return (int) $store->getId();
    }
}
