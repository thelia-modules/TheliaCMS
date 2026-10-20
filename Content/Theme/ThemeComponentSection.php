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

use Symfony\UX\TwigComponent\ComponentRendererInterface;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Content\PropType;
use TheliaCMS\Content\SectionDefinitionInterface;

/**
 * A Twig component of the theme, as a section of a document.
 *
 * Named as the theme names it (`Molecules:Reinsurance:Base`), described by
 * what its template documents, rendered by the component renderer of Symfony
 * UX: the markup is the theme's own, so the stylesheet the theme built already
 * knows every class in it.
 */
final readonly class ThemeComponentSection implements SectionDefinitionInterface
{
    /**
     * @param array<string, string> $twigNames the name of each prop as the component spells it, by the name the schema uses
     * @param list<string>          $idLists   the props that hold several records, handed to the component as a list of ids
     */
    public function __construct(
        private string $component,
        private string $description,
        private PropSchema $schema,
        private array $twigNames,
        private ComponentRendererInterface $renderer,
        private array $idLists = [],
    ) {
    }

    public function name(): string
    {
        return $this->component;
    }

    public function label(): string
    {
        $segments = explode(':', $this->component);

        return implode(' ', \array_slice($segments, -2));
    }

    public function description(): string
    {
        if ('' !== $this->description) {
            return $this->description;
        }

        return \sprintf('The "%s" component of the theme, as it is used on the rest of the site.', $this->label());
    }

    public function schema(): PropSchema
    {
        return $this->schema;
    }

    public function sample(): array
    {
        $sample = [];

        foreach ($this->schema->props as $prop) {
            $sample[$prop->name] = match ($prop->type) {
                PropType::Text => '' !== (string) $prop->default ? $prop->default : ($prop->required ? ucfirst(str_replace('_', ' ', $prop->name)) : ''),
                PropType::Url => '' !== (string) $prop->default ? $prop->default : '#',
                PropType::Image => null,
                PropType::Reference => $prop->required ? 1 : null,
                PropType::Items => \in_array($prop->name, $this->idLists, true) ? [['id' => 1], ['id' => 2]] : [],
                default => $prop->default,
            };
        }

        return $sample;
    }

    public function normalizeProps(array $props): array
    {
        return $this->schema->normalize($props);
    }

    public function render(array $props, string $locale): string
    {
        $forComponent = [];

        foreach ($props as $name => $value) {
            // A prop left empty is left to the default of the component, which
            // may be an expression the reader could not evaluate.
            if ('' === $value || null === $value) {
                continue;
            }

            // Several records are stored as rows of ids, which the hand-edit
            // form and the model both write; the component wants the ids.
            if (\in_array($name, $this->idLists, true) && \is_array($value)) {
                $value = array_values(array_filter(array_map(static fn (mixed $row): int => (int) (\is_array($row) ? ($row['id'] ?? 0) : $row), $value)));
            }

            $forComponent[$this->twigNames[$name] ?? $name] = $value;
        }

        return $this->renderer->createAndRender($this->component, $forComponent);
    }
}
