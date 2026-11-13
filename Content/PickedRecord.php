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

namespace TheliaCMS\Content;

/**
 * A record of the shop a `reference` prop of a component names by its id, and
 * the list the editor picks it from: the same for a component of the theme
 * and for a component of the site.
 */
enum PickedRecord: string
{
    case Product = 'product';
    case Category = 'category';
    case Folder = 'folder';
    case Form = 'form';
    case Menu = 'menu';

    /** The route of the list a screen picks the record from, `{id, name}` rows. */
    public function pickerRoute(): string
    {
        return match ($this) {
            self::Product => 'admin.cms.partials.sources.products',
            self::Category => 'admin.cms.partials.sources.categories',
            self::Folder => 'admin.cms.partials.sources.folders',
            self::Form => 'admin.cms.partials.sources.forms',
            self::Menu => 'admin.cms.partials.sources.menus',
        };
    }

    /** Too many to list in a select: the editor searches them. */
    public function isSearched(): bool
    {
        return self::Product === $this || self::Category === $this;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $record): string => $record->value, self::cases());
    }
}
