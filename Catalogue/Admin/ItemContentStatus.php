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

namespace TheliaCMS\Catalogue\Admin;

use TheliaCMS\Content\Document;
use TheliaCMS\Model\CmsItemContent;

/**
 * Where the document of an item stands in one language. An item is online on
 * its own: only its document has a state here.
 */
enum ItemContentStatus: string
{
    case Empty = 'empty';
    case Draft = 'draft';
    case Published = 'published';
    case ModifiedSincePublish = 'modified';

    public static function resolve(?CmsItemContent $content): self
    {
        if (null === $content?->getPublishedAt()) {
            $draft = Document::fromJson($content?->getDraftDocument());

            return null === $draft || $draft->isEmpty() ? self::Empty : self::Draft;
        }

        $draftUpdatedAt = $content->getUpdatedAt();

        if (null !== $draftUpdatedAt && $draftUpdatedAt > $content->getPublishedAt()) {
            return self::ModifiedSincePublish;
        }

        return self::Published;
    }

    public function label(): string
    {
        return match ($this) {
            self::Empty => 'No content',
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::ModifiedSincePublish => 'Modified since publication',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Empty => 'text-bg-light',
            self::Draft => 'bg-secondary',
            self::Published => 'bg-success',
            self::ModifiedSincePublish => 'bg-warning text-dark',
        };
    }
}
