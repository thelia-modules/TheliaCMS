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
 * The JSON schema of a document, built from the catalogue.
 *
 * Two readings of it. The full one says everything the props declare: the
 * shape of every section, its ranges and lengths; it is what the validator
 * enforces and what documents the catalogue. The strict one is what a
 * generator is constrained with, and it is deliberately small: the grammar
 * a structured output is compiled into cannot hold twenty sections with
 * their props (the API caps optional properties, union types and the size of
 * the grammar), so the generator is held to the envelope — a list of
 * sections, each naming a component of the catalogue and carrying its props
 * as a JSON object written as a string — and the validator reads the props
 * back and checks them against the full schema, sending the document back
 * for correction when they are wrong.
 */
final readonly class DocumentSchema
{
    public function __construct(
        private SectionCatalog $catalog,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function full(): array
    {
        $sections = [];

        foreach ($this->catalog->all() as $definition) {
            $sections[] = [
                'type' => 'object',
                'description' => $definition->description(),
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'Unique in the page: lowercase letters, digits, hyphens and underscores.'],
                    'component' => ['type' => 'string', 'const' => $definition->name()],
                    'props' => $definition->schema()->toJsonSchema(),
                ],
                'required' => ['id', 'component', 'props'],
                'additionalProperties' => false,
            ];
        }

        return [
            'type' => 'object',
            'properties' => [
                'version' => ['type' => 'integer', 'const' => Document::VERSION],
                'sections' => ['type' => 'array', 'items' => ['anyOf' => $sections]],
            ],
            'required' => ['version', 'sections'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param list<string> $kept components of the catalogue the site no longer
     *                           offers, allowed all the same because the
     *                           document being revised or translated uses them
     *
     * @return array<string, mixed>
     */
    public function strict(array $kept = []): array
    {
        $names = $this->catalog->offeredNames();

        foreach ($kept as $name) {
            if (!\in_array($name, $names, true) && null !== $this->catalog->find($name)) {
                $names[] = $name;
            }
        }

        return [
            'type' => 'object',
            'properties' => (object) [
                'version' => ['type' => 'integer', 'const' => Document::VERSION],
                'sections' => [
                    'type' => 'array',
                    'description' => 'The sections of the page, in order.',
                    'items' => [
                        'type' => 'object',
                        'properties' => (object) [
                            'id' => ['type' => 'string', 'description' => 'Unique in the page: lowercase letters, digits, hyphens and underscores, saying what the section is.'],
                            'component' => ['type' => 'string', 'enum' => $names, 'description' => 'The name of a component of the catalogue.'],
                            'props' => ['type' => 'string', 'description' => 'The props of the component, as a JSON object written as a string, with the keys and the values the catalogue describes for this component.'],
                        ],
                        'required' => ['id', 'component', 'props'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
            'required' => ['version', 'sections'],
            'additionalProperties' => false,
        ];
    }
}
