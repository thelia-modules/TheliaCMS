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

namespace TheliaCMS\Menu;

/**
 * What a menu entry points at.
 *
 * Stored as a VARCHAR: Propel maps its own ENUM type to a positional TINYINT,
 * which makes the column unreadable outside the ORM.
 */
enum MenuTargetType: string
{
    case Page = 'page';
    case Content = 'content';
    case Folder = 'folder';
    case Url = 'url';
    /** A heading in the menu: a label, no link. */
    case None = 'none';

    public static function fromStorage(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::None;
    }

    /**
     * Whether the entry points at a row of another table, and therefore needs
     * `target_id`.
     */
    public function needsTargetId(): bool
    {
        return \in_array($this, [self::Page, self::Content, self::Folder], true);
    }

    /**
     * Label shown in the back office, translated through the module domain.
     */
    public function label(): string
    {
        return match ($this) {
            self::Page => 'CMS page',
            self::Content => 'Content',
            self::Folder => 'Folder',
            self::Url => 'Web address',
            self::None => 'Label only',
        };
    }
}
