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

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Every component a document may use, by name.
 *
 * The definitions registered one by one come first, then what the providers
 * bring. The first definition claiming a name wins: a module that ships a
 * component under a name this module already uses does not replace it, which
 * is the same rule the partial registry applies.
 */
final class SectionCatalog
{
    public const string TAG = 'thelia_cms.section';

    public const string PROVIDER_TAG = 'thelia_cms.section_provider';

    /** @var array<string, SectionDefinitionInterface>|null */
    private ?array $byName = null;

    /**
     * @param iterable<SectionDefinitionInterface> $definitions
     * @param iterable<SectionProviderInterface>   $providers
     */
    public function __construct(
        #[AutowireIterator(self::TAG)]
        private readonly iterable $definitions,
        #[AutowireIterator(self::PROVIDER_TAG)]
        private readonly iterable $providers = [],
    ) {
    }

    public function find(string $name): ?SectionDefinitionInterface
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array<string, SectionDefinitionInterface>
     */
    public function all(): array
    {
        if (null !== $this->byName) {
            return $this->byName;
        }

        $byName = [];

        foreach ($this->definitions as $definition) {
            $byName[$definition->name()] ??= $definition;
        }

        foreach ($this->providers as $provider) {
            foreach ($provider->sections() as $definition) {
                $byName[$definition->name()] ??= $definition;
            }
        }

        return $this->byName = $byName;
    }
}
