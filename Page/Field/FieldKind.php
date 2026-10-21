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

namespace TheliaCMS\Page\Field;

/**
 * What a field of a page type holds. Few on purpose: each one is a value a
 * list can sort or filter on, and a template prints without guessing.
 */
enum FieldKind: string
{
    case Integer = 'integer';
    case Decimal = 'decimal';
    /** A number of minutes: a cooking time, the length of a visit. */
    case Duration = 'duration';
    /** One line of plain text. */
    case Text = 'text';
    /** Several lines of plain text, never HTML. */
    case LongText = 'longtext';
    case Boolean = 'boolean';
    /** One of the words the definition lists. */
    case Choice = 'choice';
    /** A day, written `YYYY-MM-DD`. */
    case Date = 'date';

    /**
     * Only words are written again for each language: a number of servings,
     * a duration or a date is the same fact in every one of them, and a
     * choice is a word the site picked once.
     */
    public function mayBeTranslated(): bool
    {
        return self::Text === $this || self::LongText === $this;
    }

    public function isNumber(): bool
    {
        return self::Integer === $this || self::Decimal === $this || self::Duration === $this;
    }

    /**
     * The name of the kind in the back office, translated by the template.
     */
    public function label(): string
    {
        return match ($this) {
            self::Integer => 'Whole number',
            self::Decimal => 'Decimal number',
            self::Duration => 'Duration (minutes)',
            self::Text => 'Short text',
            self::LongText => 'Long text',
            self::Boolean => 'Yes or no',
            self::Choice => 'Choice from a list',
            self::Date => 'Date',
        };
    }
}
