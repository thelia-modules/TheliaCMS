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

namespace TheliaCMS\Generation\Ai;

use Thelia\Domain\Ai\AiToolDefinition;
use TheliaCMS\Generation\Tool\GeneratorTools;

/**
 * The tools of the site as the AI layer of the core takes them. The model
 * calls them by name, the generator runs them through GeneratorTools.
 */
final readonly class GeneratorToolDefinitions
{
    public function __construct(
        private GeneratorTools $tools,
    ) {
    }

    /**
     * @return list<AiToolDefinition>
     */
    public function all(): array
    {
        return $this->convert($this->tools->definitions());
    }

    /**
     * Some of the tools only, in the stable order of all().
     *
     * @param list<string> $names
     *
     * @return list<AiToolDefinition>
     */
    public function of(array $names): array
    {
        return $this->convert($this->tools->definitionsOf($names));
    }

    /**
     * @param list<array{name: string, description: string, inputSchema: array<string, mixed>}> $definitions
     *
     * @return list<AiToolDefinition>
     */
    private function convert(array $definitions): array
    {
        return array_map(
            static fn (array $definition): AiToolDefinition => new AiToolDefinition($definition['name'], $definition['description'], $definition['inputSchema']),
            $definitions,
        );
    }
}
