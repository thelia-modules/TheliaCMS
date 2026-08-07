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

namespace TheliaCMS\Block;

use TheliaCMS\Model\CmsBlockContent;

/**
 * Publication state of a reusable block, per locale.
 *
 * A block has no publication window and no visibility of its own: it is online
 * where it is used, so the only question is whether the version pages show is
 * the one the editor last saved.
 */
enum BlockStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case ModifiedSincePublish = 'modified';

    public static function resolve(?CmsBlockContent $content): self
    {
        if (null === $content?->getPublishedAt()) {
            return self::Draft;
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
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::ModifiedSincePublish => 'Modified since publication',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-secondary',
            self::Published => 'bg-success',
            self::ModifiedSincePublish => 'bg-warning text-dark',
        };
    }
}
