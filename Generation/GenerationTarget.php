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

use TheliaCMS\Catalogue\CatalogueItem;
use TheliaCMS\Content\Document;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Page\Field\FieldDefinitions;
use TheliaCMS\Page\PageTypeDetails;

/**
 * What the generator writes for: a page — what to call it, its type and what
 * the editor said about it — and, when its type is a template of pages, what
 * every page of the type is about and the fields it carries.
 */
final readonly class GenerationTarget
{
    public const string TYPE_PAGE = 'page';

    /** A component of the site, made in the back office: its calls go in the same ledger. */
    public const string TYPE_COMPONENT = 'component';

    /**
     * @param array<string, int|float|string|bool|null> $fieldValues   the values the page holds, by field code
     * @param Document|null                             $modelDocument the draft of the model of the type in this language, when the page is not that model
     * @param CatalogueItem|null                        $item          the product, category, content or folder the document belongs to, for a document of the catalogue
     */
    private function __construct(
        public string $type,
        public int $id,
        public string $title,
        public ?string $pageType,
        public ?string $summary,
        public ?PageTypeDetails $typeDetails = null,
        public array $fieldValues = [],
        public string $locale = '',
        public ?Document $modelDocument = null,
        public ?CatalogueItem $item = null,
    ) {
    }

    /**
     * @param array<string, int|float|string|bool|null> $fieldValues
     */
    public static function page(CmsPage $page, string $locale, ?PageTypeDetails $typeDetails = null, array $fieldValues = [], ?Document $modelDocument = null): self
    {
        $page->setLocale($locale);
        $summary = trim(strip_tags((string) $page->getChapo()));

        return new self(
            self::TYPE_PAGE,
            (int) $page->getId(),
            trim((string) $page->getTitle()),
            (string) $page->getPageType(),
            '' === $summary ? null : $summary,
            $typeDetails,
            $fieldValues,
            $locale,
            $modelDocument,
        );
    }

    /**
     * The document of an item of the catalogue: its type (`product`,
     * `category`, `content`, `folder`) is the type of the target, so the
     * ledger of the generations keeps the calls of each item apart.
     */
    public static function item(CatalogueItem $item): self
    {
        return new self(
            $item->type->value,
            $item->id,
            $item->title,
            null,
            $item->summary,
            locale: $item->locale,
            item: $item,
        );
    }

    /**
     * The object a rich-text field belongs to: a page or an item of the shop
     * (`product`, `category`, `brand`, `folder`, `content`), named as its form
     * holds it, saved or not.
     */
    public static function field(string $type, int $id, string $title, string $locale): self
    {
        return new self($type, $id, trim($title), null, null, locale: $locale);
    }

    /**
     * The brief every page of the type is written from, before its own.
     */
    public function typeBrief(): ?Brief
    {
        return $this->typeDetails?->brief();
    }

    /**
     * The rules every page of the type is written by, as markdown.
     */
    public function typeInstructions(): ?string
    {
        return $this->typeDetails?->instructions();
    }

    public function fields(): FieldDefinitions
    {
        return $this->typeDetails?->fields() ?? FieldDefinitions::none();
    }

    /**
     * The name of the type in the language of the page, else its code.
     */
    public function typeTitle(): string
    {
        return $this->typeDetails?->title($this->locale) ?? (string) $this->pageType;
    }
}
