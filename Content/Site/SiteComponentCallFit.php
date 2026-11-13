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
     * component it is passed to: another type, or a list whose rows lack a
     * required field or give it another type.
     *
     * @return list<string>
     */
    public static function mismatches(Prop $given, Prop $read, SiteComponentCall $call): array
    {
        // Both print as text, the markup as it is: either reads as the other.
        $textual = [PropType::Text, PropType::Html];

        if ($given->type !== $read->type && !(\in_array($given->type, $textual, true) && \in_array($read->type, $textual, true))) {
            return [\sprintf('Line %d: props.%s is of type %s, and the prop "%s" of %s of type %s.', $call->line, $given->name, $given->type->value, $read->name, $call->code, $read->type->value)];
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

            if ($rows[$field->name]->type !== $field->type && !(\in_array($rows[$field->name]->type, $textual, true) && \in_array($field->type, $textual, true))) {
                $problems[] = \sprintf('Line %d: "%s" of the rows of props.%s is of type %s, and %s reads it as of type %s.', $call->line, $field->name, $given->name, $rows[$field->name]->type->value, $call->code, $field->type->value);
            }
        }

        return $problems;
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
