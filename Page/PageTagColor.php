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

namespace TheliaCMS\Page;

/**
 * The colors a tag may take: a closed palette rather than a free code, so the
 * text of a badge stays readable and a theme can map each name to its own
 * design. The name is what `cms_tag.color` stores and what a theme reads.
 */
enum PageTagColor: string
{
    case Red = 'red';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Green = 'green';
    case Cyan = 'cyan';
    case Dark = 'dark';

    /** The badge of a tag with no color, as every tag was shown before. */
    public const string NO_COLOR_BADGE = 'text-bg-secondary';

    /**
     * A name the palette no longer has, or one written by hand in the table,
     * reads as no color.
     */
    public static function fromStored(?string $value): ?self
    {
        return null === $value ? null : self::tryFrom($value);
    }

    public static function badgeClassOf(?self $color): string
    {
        return $color?->badgeClass() ?? self::NO_COLOR_BADGE;
    }

    public function label(): string
    {
        return match ($this) {
            self::Red => 'Red',
            self::Orange => 'Orange',
            self::Yellow => 'Yellow',
            self::Green => 'Green',
            self::Cyan => 'Cyan',
            self::Dark => 'Dark',
        };
    }

    /**
     * The paired `text-bg-*` classes of the back office: each one picks the
     * foreground that reads on its background, black on yellow, cyan and the
     * orange of Thelia, white on the others.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Red => 'text-bg-danger',
            self::Orange => 'text-bg-primary',
            self::Yellow => 'text-bg-warning',
            self::Green => 'text-bg-success',
            self::Cyan => 'text-bg-info',
            self::Dark => 'text-bg-dark',
        };
    }
}
