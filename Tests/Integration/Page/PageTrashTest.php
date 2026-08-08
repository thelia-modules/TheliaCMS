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

use Thelia\Model\RewritingUrlQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * What binning a page does to the pages under it.
 *
 * A descendant left behind keeps its address and keeps being served, while the
 * page above it is gone from the tree: the site then answers on a page the back
 * office no longer lists, and nobody can find it to take it down.
 */
final class PageTrashTest extends CmsIntegrationTestCase
{
    /**
     * How deep the chain built here goes.
     *
     * Chosen past the depth the walk used to stop at, which is the whole point:
     * the guard was a count of levels, so the pages below it were the ones left
     * online.
     */
    private const int DEPTH = 24;

    public function testBinningAPageBinsEveryPageUnderIt(): void
    {
        $chain = $this->chainOfPages(self::DEPTH);
        $deepest = $chain[self::DEPTH - 1];

        $binned = $this->writer()->moveToTrash($chain[0]);

        self::assertCount(self::DEPTH, $binned, 'Every page of the chain is binned, however deep it sits.');

        $reloaded = CmsPageQuery::create()->findPk($deepest->getId());

        self::assertNotNull($reloaded);
        self::assertNotNull(
            $reloaded->getDeletedAt(),
            \sprintf('The page %d levels down went to the bin with the page above it.', self::DEPTH),
        );
    }

    /**
     * The address of a binned descendant goes with it.
     *
     * Kept, it routes a visitor to a page the module no longer resolves, which
     * is a 500 and not even a 404, and the page still shows up in the sitemap.
     */
    public function testTheAddressOfADeepDescendantIsRemovedWithIt(): void
    {
        $chain = $this->chainOfPages(self::DEPTH);
        $deepest = $chain[self::DEPTH - 1];
        $address = (string) $deepest->getRewrittenUrl($this->locale());

        self::assertNotSame('', $address, 'The fixture is only meaningful if the deepest page had an address.');

        $this->writer()->moveToTrash($chain[0]);

        self::assertNull(
            RewritingUrlQuery::create()->findOneByUrl($address),
            'A page binned with its ancestor stops answering on the address it had.',
        );
    }

    /**
     * A tree holding a cycle is walked once and not followed again.
     *
     * The back office cannot build one, since the parent choices of a page leave
     * out its own descendants, but an import or a hand-written row can. Counting
     * levels made the walk visit the same two pages over and over instead of
     * stopping.
     */
    public function testATreeThatContainsItselfIsBinnedOncePerPage(): void
    {
        $top = $this->createPage('Boucle du haut');
        $child = $this->createPage('Boucle du bas', parent: (int) $top->getId());

        $top->setParent((int) $child->getId())->save();

        $binned = $this->writer()->moveToTrash($top);

        self::assertSame(
            [(int) $top->getId(), (int) $child->getId()],
            $binned,
            'Each page of the cycle is binned once, and the walk stops there.',
        );
    }

    /**
     * The listing walks the same tree downwards and needs no such guard, which
     * is worth writing down rather than leaving to the next reader: a page has
     * one parent, so a cycle is a component of its own, never reachable from the
     * root the listing starts at. Sabotaging the walk cannot make a case here
     * fail, so there is none.
     *
     * @return list<CmsPage> from the top of the chain down
     */
    private function chainOfPages(int $depth): array
    {
        $chain = [];
        $parent = 0;

        for ($level = 1; $level <= $depth; ++$level) {
            $page = $this->createPage('Niveau '.$level, parent: $parent);
            $chain[] = $page;
            $parent = (int) $page->getId();
        }

        return $chain;
    }
}
