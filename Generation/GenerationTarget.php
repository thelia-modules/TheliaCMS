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

namespace TheliaCMS\Generation;

use TheliaCMS\Content\Partial\PartialSection;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Partial\Definition\BlockPartial;

/**
 * What the generator writes for: a page, or a block — reusable, or the one of
 * a catalogue item. The generator reads the same thing of both: what to call
 * it, what the editor said about it, and which components it may not use.
 */
final readonly class GenerationTarget
{
    public const string TYPE_PAGE = 'page';
    public const string TYPE_BLOCK = 'block';

    /**
     * @param list<string> $excludedComponents components of the catalogue a document of this target may not name
     */
    private function __construct(
        public string $type,
        public int $id,
        public string $title,
        public ?string $pageType,
        public ?string $summary,
        public array $excludedComponents = [],
    ) {
    }

    public static function page(CmsPage $page, string $locale): self
    {
        $page->setLocale($locale);
        $summary = trim(strip_tags((string) $page->getChapo()));

        return new self(
            self::TYPE_PAGE,
            (int) $page->getId(),
            trim((string) $page->getTitle()),
            (string) $page->getPageType(),
            '' === $summary ? null : $summary,
        );
    }

    /**
     * A block holds no block: a block placed inside itself would never stop
     * rendering, and the reusable builder refused it for the same reason.
     *
     * @param string|null $itemLabel what the block belongs to, for the block of a catalogue item
     */
    public static function block(CmsBlock $block, string $locale, ?string $itemLabel = null): self
    {
        $block->setLocale($locale);
        $title = trim((string) $block->getTitle());

        return new self(
            self::TYPE_BLOCK,
            (int) $block->getId(),
            '' === $title ? (string) $block->getCode() : $title,
            null,
            $itemLabel,
            [PartialSection::PREFIX.BlockPartial::NAME],
        );
    }

    public function isPage(): bool
    {
        return self::TYPE_PAGE === $this->type;
    }

    public function isBlock(): bool
    {
        return self::TYPE_BLOCK === $this->type;
    }
}
