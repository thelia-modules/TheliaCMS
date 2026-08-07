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

namespace TheliaCMS\Form;

/**
 * The kinds of field a form may hold.
 *
 * Nine, and no more in this version: every extra type is a rendering, a
 * validation rule, an export column and a translation to keep working, and the
 * ones below cover what a contact, a quote request or a callback form asks for.
 */
enum FieldType: string
{
    case Text = 'text';
    case Email = 'email';
    case Textarea = 'textarea';
    case Select = 'select';
    case Checkbox = 'checkbox';
    case Radio = 'radio';
    case Phone = 'phone';
    case Date = 'date';

    /**
     * A tick box recording an agreement.
     *
     * Never ticked in advance, stamped with the moment it was given, and stored
     * along with the exact sentence the visitor read: an agreement nobody can
     * show the wording of is not an agreement. Since 11 August 2026 this is what
     * makes a phone number usable for a commercial call in France.
     */
    case Consent = 'consent';

    public static function fromStorage(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Text;
    }

    /**
     * Label of the type in the back office, as a translation key.
     */
    public function label(): string
    {
        return match ($this) {
            self::Text => 'Single line of text',
            self::Email => 'Email address',
            self::Textarea => 'Several lines of text',
            self::Select => 'Drop-down list',
            self::Checkbox => 'Tick box',
            self::Radio => 'One choice among several',
            self::Phone => 'Phone number',
            self::Date => 'Date',
            self::Consent => 'Agreement to be contacted',
        };
    }

    /**
     * Whether the field is answered by picking from a written list.
     */
    public function hasChoices(): bool
    {
        return self::Select === $this || self::Radio === $this;
    }

    /**
     * Whether the answer is a yes or a no rather than a value.
     */
    public function isTickBox(): bool
    {
        return self::Checkbox === $this || self::Consent === $this;
    }
}
