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

namespace TheliaCMS\Partial;

/**
 * One setting of a partial block: what the editor shows in the settings panel,
 * and what the server accepts before handing it to a template.
 */
final readonly class PartialProp
{
    /**
     * @param array<string, string> $choices    value => label, for a Choice prop
     * @param string|null           $source     endpoint feeding a Reference prop with its options
     * @param int|null              $min        lower bound of an Integer, or minimum length of a Text
     * @param int|null              $max        upper bound of an Integer, or maximum length of a Text
     * @param bool                  $searchable whether the editor searches the source of a Reference
     *                                          rather than listing all of it
     * @param string|null           $itemName   the key each record of a References prop is stored under
     * @param string|null           $itemLabel  what one record of a References prop is called
     */
    public function __construct(
        public string $name,
        public PartialPropType $type,
        public string $label,
        public string|int|bool|null $default = null,
        public bool $required = false,
        public array $choices = [],
        public ?string $source = null,
        public ?int $min = null,
        public ?int $max = null,
        public ?string $help = null,
        public bool $searchable = false,
        public ?string $itemName = null,
        public ?string $itemLabel = null,
    ) {
    }

    public static function text(string $name, string $label, ?string $default = null, bool $required = false, int $max = 255, ?string $help = null): self
    {
        return new self($name, PartialPropType::Text, $label, $default, $required, max: $max, help: $help);
    }

    public static function integer(string $name, string $label, int $default, int $min, int $max, ?string $help = null): self
    {
        return new self($name, PartialPropType::Integer, $label, $default, min: $min, max: $max, help: $help);
    }

    public static function boolean(string $name, string $label, bool $default = false, ?string $help = null): self
    {
        return new self($name, PartialPropType::Boolean, $label, $default, help: $help);
    }

    /**
     * @param array<string, string> $choices
     */
    public static function choice(string $name, string $label, array $choices, string $default, ?string $help = null): self
    {
        return new self($name, PartialPropType::Choice, $label, $default, choices: $choices, help: $help);
    }

    /**
     * A record picked from `$source`, which answers a list of `{id, name}`.
     *
     * By default the editor loads the whole list into a select, which suits a
     * few dozen records. A source too long for that is `$searchable`: the
     * editor sends what the author types as `?q=` and lists what comes back,
     * and asks `?id=` for the name of the record already chosen. The source
     * then answers both, still as a list.
     */
    public static function reference(string $name, string $label, string $source, bool $required = true, ?string $help = null, bool $searchable = false): self
    {
        return new self($name, PartialPropType::Reference, $label, required: $required, source: $source, help: $help, searchable: $searchable);
    }

    /**
     * Several records picked from `$source`, stored as a list of
     * `{<itemName>: id}` in the order they were picked: a strip of products,
     * a row of categories. `$min` of them are required.
     */
    public static function references(string $name, string $label, string $source, string $itemName, int $min = 0, int $max = 12, ?string $help = null, bool $searchable = false, ?string $itemLabel = null): self
    {
        return new self($name, PartialPropType::References, $label, required: $min > 0, source: $source, min: $min, max: $max, help: $help, searchable: $searchable, itemName: $itemName, itemLabel: $itemLabel);
    }

    /**
     * The shape the editor needs to build a trait for this setting.
     *
     * @return array<string, mixed>
     */
    public function toEditor(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type->value,
            'label' => $this->label,
            'default' => $this->default,
            'choices' => $this->choices,
            'source' => $this->source,
            'min' => $this->min,
            'max' => $this->max,
            'help' => $this->help,
            'searchable' => $this->searchable ?: null,
        ], static fn (mixed $value): bool => null !== $value && [] !== $value);
    }
}
