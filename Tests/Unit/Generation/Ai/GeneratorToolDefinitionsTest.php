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

namespace TheliaCMS\Tests\Unit\Generation\Ai;

use PHPUnit\Framework\TestCase;
use Thelia\Domain\Ai\AiToolDefinition;
use TheliaCMS\Generation\Ai\GeneratorToolDefinitions;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\Tool\GeneratorToolInterface;
use TheliaCMS\Generation\Tool\GeneratorTools;

/**
 * The tools of the site, described to the AI layer of the core.
 */
final class GeneratorToolDefinitionsTest extends TestCase
{
    public function testEveryToolBecomesADefinitionWithItsNameDescriptionAndSchema(): void
    {
        $definitions = $this->definitions()->all();

        self::assertContainsOnlyInstancesOf(AiToolDefinition::class, $definitions);
        self::assertCount(2, $definitions);
        self::assertSame('search_pages', $definitions[0]->name);
        self::assertSame('Finds pages.', $definitions[0]->description);
        self::assertSame(['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'additionalProperties' => false], $definitions[0]->inputSchema);
    }

    public function testTheDefinitionsKeepTheStableOrderOfTheTools(): void
    {
        $names = array_map(static fn (AiToolDefinition $definition): string => $definition->name, $this->definitions()->all());

        self::assertSame(['search_pages', 'search_products'], $names);
    }

    public function testOnlyTheToolsNamedAreDescribed(): void
    {
        $definitions = $this->definitions()->of(['search_products', 'unknown_tool']);

        self::assertCount(1, $definitions);
        self::assertSame('search_products', $definitions[0]->name);
    }

    public function testNoToolNamedMeansNoDefinition(): void
    {
        self::assertSame([], $this->definitions()->of([]));
    }

    private function definitions(): GeneratorToolDefinitions
    {
        return new GeneratorToolDefinitions(new GeneratorTools([
            self::tool('search_products', 'Finds products.'),
            self::tool('search_pages', 'Finds pages.'),
        ]));
    }

    private static function tool(string $name, string $description): GeneratorToolInterface
    {
        return new readonly class($name, $description) implements GeneratorToolInterface {
            public function __construct(private string $name, private string $description)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function description(): string
            {
                return $this->description;
            }

            public function inputSchema(): array
            {
                return ['type' => 'object', 'properties' => ['query' => ['type' => 'string']], 'additionalProperties' => false];
            }

            public function run(array $input, GenerationRequest $request): string
            {
                return '';
            }
        };
    }
}
