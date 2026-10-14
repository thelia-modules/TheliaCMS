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

namespace TheliaCMS\Block\Item;

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Model\CmsBlockContentQuery;
use TheliaCMS\Model\CmsItemBlock;
use TheliaCMS\Partial\PartialRenderer;

/**
 * The single front-office read path of item blocks.
 *
 * Only the published version, and only in the requested language: a draft
 * must not reach a visitor through the page of its product, and a block
 * written in French must not appear on an English page.
 */
final readonly class PublishedItemBlockRepository
{
    public function __construct(
        private ItemBlockRepository $itemBlocks,
        private PartialRenderer $partials,
    ) {
    }

    public function find(ItemType $type, int $itemId, string $locale): ?PublishedItemBlock
    {
        $link = $this->itemBlocks->linkFor($type, $itemId);

        if (!$link instanceof CmsItemBlock) {
            return null;
        }

        $content = CmsBlockContentQuery::create()
            ->filterByBlockId($link->getBlockId())
            ->filterByLocale($locale)
            ->filterByPublishedAt(null, Criteria::ISNOTNULL)
            ->useCmsBlockQuery()
                ->filterByDeletedAt(null, Criteria::ISNULL)
            ->endUse()
            ->findOne();

        if (null === $content) {
            return null;
        }

        return new PublishedItemBlock(
            type: $type,
            itemId: $itemId,
            locale: $locale,
            html: (string) $this->partials->substitute($content->getPublishedHtml(), $locale),
            css: (string) $content->getPublishedCss(),
            publishedAt: $content->getPublishedAt(),
        );
    }
}
