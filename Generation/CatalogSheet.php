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

namespace TheliaCMS\Generation;

use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropType;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Content\SectionDefinitionInterface;

/**
 * The catalogue as the model reads it: every section with what it is for,
 * its props and one example. Written in the same order at every call, so
 * the prefix of the prompt stays the same and the cache holds.
 */
final readonly class CatalogSheet
{
    public function __construct(
        private SectionCatalog $catalog,
    ) {
    }

    public function text(): string
    {
        $lines = ["# The catalogue\n", 'The components a section may name, with their props. `*` marks a required prop.', ''];

        foreach ($this->catalog->all() as $definition) {
            $lines[] = $this->section($definition);
        }

        return implode("\n", $lines);
    }

    public function section(SectionDefinitionInterface $definition): string
    {
        $lines = [\sprintf('## `%s` — %s', $definition->name(), $definition->label()), '', $definition->description(), '', 'Props:'];

        foreach ($definition->schema()->props as $prop) {
            $lines[] = self::propLine($prop, '- ');
        }

        $lines[] = '';
        $lines[] = 'Example:';
        $lines[] = '```json';
        $lines[] = json_encode(['id' => self::exampleId($definition->name()), 'component' => $definition->name(), 'props' => $definition->sample()], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
        $lines[] = '```';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private static function propLine(Prop $prop, string $indent): string
    {
        $line = \sprintf('%s`%s`%s (%s)', $indent, $prop->name, $prop->required ? '*' : '', self::typeOf($prop));

        if ('' !== $prop->label && $prop->label !== $prop->name) {
            $line .= ': '.$prop->label;
        }

        if (null !== $prop->help && '' !== $prop->help) {
            $line .= '. '.rtrim($prop->help, '.').'.';
        }

        if (PropType::Items === $prop->type) {
            $items = array_map(static fn (Prop $item): string => self::propLine($item, $indent.'  - '), $prop->items);
            $line .= "\n".implode("\n", $items);
        }

        return $line;
    }

    private static function typeOf(Prop $prop): string
    {
        return match ($prop->type) {
            PropType::Text => 'text'.(null !== $prop->max ? ', '.$prop->max.' characters at most' : ''),
            PropType::Html => 'html',
            PropType::Integer => \sprintf('integer, %d to %d', $prop->min ?? 0, $prop->max ?? 0),
            PropType::Boolean => 'true or false',
            PropType::Choice => 'one of '.implode(', ', array_map(static fn (string|int $value): string => '`'.$value.'`', array_keys($prop->choices))),
            PropType::Image => 'image id from search_media, an https address the brief gives, or null',
            PropType::Url => 'url',
            PropType::Items => \sprintf('list of %d to %d items', $prop->minItems ?? 0, $prop->maxItems ?? 12),
            PropType::Reference => 'id from the search tools',
            PropType::Json => 'JSON string',
        };
    }

    private static function exampleId(string $component): string
    {
        $segments = explode(':', $component);

        return strtolower(preg_replace('/[^a-z0-9]+/i', '-', end($segments) ?: 'section') ?? 'section');
    }
}
