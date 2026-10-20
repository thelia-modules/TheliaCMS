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

namespace TheliaCMS\Generation\Tool;

use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Generation\CatalogSheet;
use TheliaCMS\Generation\GenerationRequest;

/**
 * The full sheet of one section, props and example: what the model asks for
 * when the catalogue summary is not enough.
 */
final readonly class ReadSectionTool implements GeneratorToolInterface
{
    public function __construct(
        private SectionCatalog $catalog,
        private CatalogSheet $sheet,
    ) {
    }

    public function name(): string
    {
        return 'read_section';
    }

    public function description(): string
    {
        return 'Reads the full description of one component of the catalogue: every prop with its constraints, and a complete example.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['component' => ['type' => 'string', 'description' => 'The name of the component, as the catalogue spells it.']],
            'required' => ['component'],
            'additionalProperties' => false,
        ];
    }

    public function run(array $input, GenerationRequest $request): string
    {
        $definition = $this->catalog->find(trim((string) ($input['component'] ?? '')));

        if (null === $definition) {
            return 'The catalogue has no such component. The names are: '.implode(', ', $this->catalog->names()).'.';
        }

        return $this->sheet->section($definition);
    }
}
