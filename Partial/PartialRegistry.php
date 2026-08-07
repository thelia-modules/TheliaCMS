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

namespace TheliaCMS\Partial;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * The list of partials a page may use.
 *
 * It is also the allow list of the whole feature: the renderer and the preview
 * endpoint resolve a template through this registry and nowhere else, so a
 * `data-cms-partial` naming anything unknown renders nothing instead of
 * reaching for a file path taken from the page.
 */
final readonly class PartialRegistry
{
    public const string TAG = 'thelia_cms.partial';

    /** @var array<string, PartialDefinitionInterface> */
    private array $definitions;

    /**
     * @param iterable<PartialDefinitionInterface> $definitions
     */
    public function __construct(
        #[AutowireIterator(self::TAG)]
        iterable $definitions = [],
    ) {
        $indexed = [];

        foreach ($definitions as $definition) {
            $indexed[$definition->name()] = $definition;
        }

        $this->definitions = $indexed;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function find(string $name): ?PartialDefinitionInterface
    {
        return $this->definitions[$name] ?? null;
    }

    /**
     * @return list<PartialDefinitionInterface>
     */
    public function all(): array
    {
        return array_values($this->definitions);
    }

    /**
     * What the editor needs to offer these blocks: one entry per partial, with
     * its settings already described.
     *
     * @return list<array<string, mixed>>
     */
    public function toEditor(): array
    {
        return array_map(
            static fn (PartialDefinitionInterface $definition): array => [
                'name' => $definition->name(),
                'label' => $definition->label(),
                'props' => array_map(
                    static fn (PartialProp $prop): array => $prop->toEditor(),
                    $definition->props(),
                ),
            ],
            $this->all(),
        );
    }
}
