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
use TheliaCMS\Page\Admin\PageDraft;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The address of a page inside the tree.
 *
 * What a visitor and a search engine expect is that a child sits under the path
 * of its parent. That holds only if the prefix is read from the address the
 * parent answers on, and not rebuilt from its title, which is a different
 * string as soon as anyone edits a slug by hand.
 */
final class PageAddressTest extends CmsIntegrationTestCase
{
    public function testAChildSitsUnderTheAddressItsParentAnswersOn(): void
    {
        $parent = $this->pageWithSlug('Le Groupe', 'groupe');

        $child = $this->createPage('Agence web Lyon', parent: (int) $parent->getId());

        self::assertSame('groupe', $parent->getRewrittenUrl($this->locale()));
        self::assertSame('groupe/agence-web-lyon', $child->getRewrittenUrl($this->locale()));
    }

    public function testEveryLevelOfTheTreeFollowsTheLevelAboveIt(): void
    {
        $top = $this->pageWithSlug('Nos métiers', 'metiers');
        $middle = $this->pageWithSlug('Développement web sur mesure', 'developpement-sur-mesure-2025', (int) $top->getId());

        $leaf = $this->createPage('CRM', parent: (int) $middle->getId());

        self::assertSame('metiers/developpement-sur-mesure-2025', $middle->getRewrittenUrl($this->locale()));
        self::assertSame('metiers/developpement-sur-mesure-2025/crm', $leaf->getRewrittenUrl($this->locale()));
    }

    /**
     * The everyday case, where nobody edited a slug: the address is unchanged.
     */
    public function testAnUntouchedSlugStillComposesTheAddressFromTheTitles(): void
    {
        $parent = $this->createPage('Nos engagements');
        $child = $this->createPage('Notre charte', parent: (int) $parent->getId());

        self::assertSame('nos-engagements', $parent->getRewrittenUrl($this->locale()));
        self::assertSame('nos-engagements/notre-charte', $child->getRewrittenUrl($this->locale()));
    }

    private function pageWithSlug(string $title, string $slug, int $parent = 0): CmsPage
    {
        $page = $this->createPage($title, parent: $parent);
        $this->writer()->saveDraft($page, $this->locale(), new PageDraft(title: $title, slug: $slug));

        return $page;
    }
}
