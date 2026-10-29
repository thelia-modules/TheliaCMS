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

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TheliaCMS\Generation\GenerationRequest;

/**
 * Every tool offered to the model, and the dispatch of its calls.
 */
final class GeneratorTools
{
    public const string TAG = 'thelia_cms.generator_tool';

    /** @var array<string, GeneratorToolInterface>|null */
    private ?array $byName = null;

    /**
     * @param iterable<GeneratorToolInterface> $tools
     */
    public function __construct(
        #[AutowireIterator(self::TAG)]
        private readonly iterable $tools,
    ) {
    }

    /**
     * The tools as the API takes them, in a stable order so the prefix of the
     * request stays the same from one call to the next.
     *
     * Not strict: a strict tool joins the grammar the output schema is
     * compiled into, and with the catalogue in it the grammar is already at
     * its limit. The inputs are one string each, and a tool answers a wrong
     * input with a sentence rather than an error.
     *
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->all() as $tool) {
            $definitions[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'inputSchema' => $tool->inputSchema(),
            ];
        }

        return $definitions;
    }

    /**
     * Some of the tools only, as definitions() gives them: a short text needs
     * the catalogue and the pages, not the pictures nor the sections.
     *
     * @param list<string> $names
     *
     * @return list<array{name: string, description: string, inputSchema: array<string, mixed>}>
     */
    public function definitionsOf(array $names): array
    {
        return array_values(array_filter(
            $this->definitions(),
            static fn (array $definition): bool => \in_array($definition['name'], $names, true),
        ));
    }

    /**
     * @param array<string, mixed> $input
     */
    public function run(string $name, array $input, GenerationRequest $request): string
    {
        $tool = $this->all()[$name] ?? null;

        if (null === $tool) {
            return \sprintf('There is no tool called "%s".', $name);
        }

        try {
            return $tool->run($input, $request);
        } catch (\Throwable $throwable) {
            return \sprintf('The tool "%s" failed: %s', $name, $throwable->getMessage());
        }
    }

    /**
     * @return array<string, GeneratorToolInterface>
     */
    private function all(): array
    {
        if (null !== $this->byName) {
            return $this->byName;
        }

        $byName = [];

        foreach ($this->tools as $tool) {
            $byName[$tool->name()] ??= $tool;
        }

        ksort($byName);

        return $this->byName = $byName;
    }
}
