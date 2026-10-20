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

namespace TheliaCMS\Content\Theme;

use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropSchema;
use Twig\Environment;

/**
 * The props of a Twig component of the theme, read off its template.
 *
 * A component of the Flexy theme documents its props twice, and both are read:
 * a comment per prop at the top of the template, `{# @prop name type what it
 * is for #}`, and the `{% props name = default, … %}` tag that declares them.
 * The comment gives the type and the meaning, the tag the default; a prop
 * declared without a default and not documented as nullable is required.
 *
 * Read at every call rather than cached: the theme is edited while the site
 * runs, and the schema has to follow the template.
 */
final readonly class ComponentPropsReader
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    /**
     * The template of a component, as the Twig loader names it, or null when
     * the theme chain has no such component.
     */
    public function templateOf(string $component): ?string
    {
        $template = '@Flexy/'.str_replace(':', '/', $component).'.html.twig';

        return $this->twig->getLoader()->exists($template) ? $template : null;
    }

    /**
     * @return array{description: string, schema: PropSchema, documented: bool}
     *
     * @throws \InvalidArgumentException when the theme chain has no such component
     */
    public function read(string $component): array
    {
        $template = $this->templateOf($component);

        if (null === $template) {
            throw new \InvalidArgumentException(\sprintf('The theme has no component "%s".', $component));
        }

        $source = $this->twig->getLoader()->getSourceContext($template)->getCode();
        $documented = self::documentedProps($source);
        $declared = self::declaredProps($source);
        $props = [];

        foreach ($declared as $name => $declaration) {
            $doc = $documented[$name] ?? ['type' => 'string', 'description' => ''];
            $props[] = self::prop($name, $doc['type'], $doc['description'], $declaration['default'], $declaration['hasDefault']);
        }

        return [
            'description' => self::description($source),
            'schema' => new PropSchema($props),
            'documented' => [] !== $documented,
        ];
    }

    /**
     * @return array<string, array{type: string, description: string}>
     */
    private static function documentedProps(string $source): array
    {
        preg_match_all('/\{#\s*@prop\s+([a-zA-Z_][a-zA-Z0-9_]*)\s+(\S+)\s*(.*?)\s*#\}/s', $source, $matches, \PREG_SET_ORDER);
        $documented = [];

        foreach ($matches as $match) {
            $documented[self::propName($match[1])] = ['type' => $match[2], 'description' => trim(preg_replace('/\s+/', ' ', $match[3]) ?? '')];
        }

        return $documented;
    }

    /**
     * The `{# @description … #}` comment of the template, when it has one.
     */
    private static function description(string $source): string
    {
        if (1 === preg_match('/\{#\s*@description\s+(.*?)\s*#\}/s', $source, $match)) {
            return trim(preg_replace('/\s+/', ' ', $match[1]) ?? '');
        }

        return '';
    }

    /**
     * @return array<string, array{default: mixed, hasDefault: bool}>
     */
    private static function declaredProps(string $source): array
    {
        if (1 !== preg_match('/\{%-?\s*props\s+(.*?)\s*-?%\}/s', $source, $match)) {
            return [];
        }

        $declared = [];

        foreach (self::splitTopLevel($match[1]) as $argument) {
            $argument = trim($argument);

            if ('' === $argument) {
                continue;
            }

            if (1 === preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.*)$/s', $argument, $parts)) {
                $declared[self::propName($parts[1])] = ['default' => self::literal(trim($parts[2])), 'hasDefault' => true];
            } elseif (1 === preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $argument)) {
                $declared[self::propName($argument)] = ['default' => null, 'hasDefault' => false];
            }
        }

        return $declared;
    }

    /**
     * Twig writes `camelCase`, a document writes `snake_case`: the same prop
     * under the name the schema accepts. Handed back to the component under
     * its own name by the section.
     */
    public static function propName(string $twigName): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $twigName) ?? $twigName);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $arguments): array
    {
        $parts = [];
        $depth = 0;
        $quote = null;
        $current = '';
        $length = \strlen($arguments);

        for ($i = 0; $i < $length; ++$i) {
            $char = $arguments[$i];

            if (null !== $quote) {
                $current .= $char;

                if ($char === $quote && '\\' !== ($arguments[$i - 1] ?? '')) {
                    $quote = null;
                }

                continue;
            }

            if ('"' === $char || "'" === $char) {
                $quote = $char;
            } elseif (\in_array($char, ['(', '[', '{'], true)) {
                ++$depth;
            } elseif (\in_array($char, [')', ']', '}'], true)) {
                --$depth;
            } elseif (',' === $char && 0 === $depth) {
                $parts[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }

    /**
     * A Twig literal as a PHP value: what a default may be. Anything that is
     * an expression — a function call, a variable — reads as null.
     */
    private static function literal(string $expression): mixed
    {
        if ('null' === $expression) {
            return null;
        }

        if (\in_array($expression, ['true', 'false'], true)) {
            return 'true' === $expression;
        }

        if (1 === preg_match('/^-?\d+$/', $expression)) {
            return (int) $expression;
        }

        if (1 === preg_match('/^-?\d*\.\d+$/', $expression)) {
            return (float) $expression;
        }

        if (1 === preg_match('/^(["\'])(.*)\1$/s', $expression, $match)) {
            return $match[2];
        }

        if (1 === preg_match('/^[\[{]/', $expression)) {
            return [];
        }

        return null;
    }

    private static function prop(string $name, string $type, string $description, mixed $default, bool $hasDefault): Prop
    {
        $nullable = str_contains($type, 'null') || null === $default && $hasDefault;
        $required = !$hasDefault && !$nullable;
        $kinds = array_map(trim(...), explode('|', $type));
        $choices = [];

        foreach ($kinds as $kind) {
            if (1 === preg_match('/^["\'](.+)["\']$/', $kind, $match)) {
                $choices[$match[1]] = $match[1];
            }
        }

        if ([] !== $choices) {
            return Prop::choice($name, $name, $choices, \is_string($default) && isset($choices[$default]) ? $default : (string) array_key_first($choices), $description);
        }

        if (array_intersect($kinds, ['bool', 'boolean'])) {
            return Prop::boolean($name, $name, (bool) $default, $description);
        }

        if (array_intersect($kinds, ['int', 'integer'])) {
            return Prop::integer($name, $name, \is_int($default) ? $default : 0, \PHP_INT_MIN, \PHP_INT_MAX, $description);
        }

        if (array_intersect($kinds, ['array', 'list', 'iterable', 'object']) || preg_grep('/^(array|list)</', $kinds)) {
            return Prop::json($name, $name, \is_array($default) ? $default : [], $description);
        }

        return Prop::text($name, $name, \is_scalar($default) ? (string) $default : '', $required, 500, $description);
    }
}
