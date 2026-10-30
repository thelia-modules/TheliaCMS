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

namespace TheliaCMS\Tests\Integration\Menu;

use Thelia\Test\FixtureFactory;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItemQuery;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Tests\Integration\Security\AdminScreenTestCase;

/**
 * The entry form of a menu offers the categories and the products, and saves
 * an entry pointing at one of them.
 */
final class CatalogueMenuEntryScreenTest extends AdminScreenTestCase
{
    public function testAnEntryPointingAtAProductIsChosenFromItsCategoryAndSaved(): void
    {
        $factory = new FixtureFactory($this->getPropelConnection());
        $category = $factory->category();
        $category->setLocale($this->locale())->setTitle('Revêtements')->save();
        $product = $factory->product($category, $factory->taxRule(), $factory->currency());
        $product->setLocale($this->locale())->setTitle('Vernis mat')->save();

        $menu = CmsMenuQuery::findShared('footer');
        self::assertInstanceOf(CmsMenu::class, $menu);
        $action = '/admin/cms/menus/'.$menu->getId().'/entries/new';

        $screen = $this->screen('/admin/cms/menus/'.$menu->getId());

        $form = $screen->filter('form')->reduce(
            static fn ($node): bool => parse_url((string) $node->attr('action'), \PHP_URL_PATH) === $action,
        );
        self::assertSame(1, $form->count(), 'The menu screen has no form to add an entry.');
        self::assertSame(
            (string) $category->getId(),
            $form->filter('select[name$="[categoryId]"] option')->reduce(static fn ($option): bool => 'Revêtements' === trim($option->text()))->attr('value'),
        );
        $group = $form->filter('select[name$="[productId]"] optgroup[label="Revêtements"]');
        self::assertSame(1, $group->count(), 'The products are grouped under their default category.');
        self::assertSame((string) $product->getId(), $group->filter('option')->reduce(static fn ($option): bool => 'Vernis mat' === trim($option->text()))->attr('value'));
        // `form_end()` prints a field the template forgot at the bottom of the
        // form, under the submit button: each target comes before the label.
        $markup = (string) $form->html();
        self::assertLessThan(strpos($markup, '[label]'), strpos($markup, '[categoryId]'));
        self::assertLessThan(strpos($markup, '[label]'), strpos($markup, '[productId]'));

        $before = CmsMenuItemQuery::create()->filterByMenuId($menu->getId())->count();
        $fields = $form->form();
        $name = (string) preg_replace('/\[targetType\]$/', '', (string) $form->filter('select[name$="[targetType]"]')->attr('name'));
        $fields[$name.'[targetType]']->select('product');
        $fields[$name.'[productId]']->select((string) $product->getId());
        $this->client->submit($fields, [], self::SAME_SITE);

        self::assertTrue($this->client->getResponse()->isRedirection(), 'The entry was refused: '.strip_tags((string) $this->client->getResponse()->getContent()));
        self::assertSame($before + 1, CmsMenuItemQuery::create()->filterByMenuId($menu->getId())->count());
        $saved = CmsMenuItemQuery::create()->filterByMenuId($menu->getId())->orderById('desc')->findOne();
        self::assertSame('product', $saved?->getTargetType());
        self::assertSame((int) $product->getId(), (int) $saved?->getTargetId());
    }
}
