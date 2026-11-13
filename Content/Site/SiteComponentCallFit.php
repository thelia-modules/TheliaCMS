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

namespace TheliaCMS\Content\Site;

use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropType;

/**
 * Whether what a `cms_component` call passes reads as the props of the
 * component it draws: read when the caller is saved, and when a new version
 * of the component it draws is kept.
 */
final class SiteComponentCallFit
{
    /**
     * Where a prop of the caller does not read as the prop of the called
     * component it is passed to: another type, a reference to another kind of
     * record, or a list whose rows lack a required field or give it another
     * type or another kind of record. An address reads as a text.
     *
     * @return list<string>
     */
    public static function mismatches(Prop $given, Prop $read, SiteComponentCall $call): array
    {
        if (!self::reads($given, $read)) {
            return [\sprintf('Line %d: props.%s is of type %s, and the prop "%s" of %s of type %s.', $call->line, $given->name, $given->type->value, $read->name, $call->code, $read->type->value)];
        }

        if (PropType::Choice === $read->type && [] !== $unknown = array_diff(array_keys($given->choices), array_keys($read->choices))) {
            return [\sprintf('Line %d: props.%s may be %s, which the prop "%s" of %s does not offer; it offers %s.', $call->line, $given->name, implode(', ', $unknown), $read->name, $call->code, implode(', ', array_keys($read->choices)))];
        }

        if (self::namesAnotherRecord($given, $read)) {
            return [\sprintf('Line %d: props.%s names a %s, and the prop "%s" of %s a %s.', $call->line, $given->name, $given->record, $read->name, $call->code, $read->record)];
        }

        if (PropType::Items !== $read->type) {
            return [];
        }

        $rows = self::byName($given->items);
        $problems = [];

        foreach ($read->items as $field) {
            if (!isset($rows[$field->name])) {
                if ($field->required) {
                    $problems[] = \sprintf('Line %d: the rows of props.%s have no "%s", which the rows of "%s" of %s need: name the fields of the rows as %s names them.', $call->line, $given->name, $field->name, $read->name, $call->code, $call->code);
                }

                continue;
            }

            if (!self::reads($rows[$field->name], $field)) {
                $problems[] = \sprintf('Line %d: "%s" of the rows of props.%s is of type %s, and %s reads it as of type %s.', $call->line, $field->name, $given->name, $rows[$field->name]->type->value, $call->code, $field->type->value);
            } elseif (self::namesAnotherRecord($rows[$field->name], $field)) {
                $problems[] = \sprintf('Line %d: "%s" of the rows of props.%s names a %s, and %s reads a %s.', $call->line, $field->name, $given->name, $rows[$field->name]->record, $call->code, $field->record);
            }
        }

        return $problems;
    }

    /**
     * Whether a value of the type given prints as the type read: the same
     * type, a text and markup either way, an address as a text.
     */
    /**
     * A value written in the call for a choice: one of its values.
     */
    public static function writtenMismatch(string|int|float|bool|null $value, Prop $read, SiteComponentCall $call): ?string
    {
        if (PropType::Choice !== $read->type || \array_key_exists((string) $value, $read->choices)) {
            return null;
        }

        return \sprintf('Line %d: "%s" is written for the prop "%s" of %s, which offers %s.', $call->line, (string) $value, $read->name, $call->code, implode(', ', array_keys($read->choices)));
    }

    private static function reads(Prop $given, Prop $read): bool
    {
        $textual = [PropType::Text, PropType::Html];

        return $given->type === $read->type
            || (\in_array($given->type, $textual, true) && \in_array($read->type, $textual, true))
            || (PropType::Url === $given->type && PropType::Text === $read->type);
    }

    private static function namesAnotherRecord(Prop $given, Prop $read): bool
    {
        return PropType::Reference === $read->type && PropType::Reference === $given->type && $given->record !== $read->record;
    }

    /**
     * @param list<Prop> $props
     *
     * @return array<string, Prop>
     */
    public static function byName(array $props): array
    {
        $named = [];

        foreach ($props as $prop) {
            $named[$prop->name] = $prop;
        }

        return $named;
    }
}
