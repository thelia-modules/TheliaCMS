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
 * One section of a page: a component of the catalogue and the values of its
 * props. The id names the section inside its document, so a later change can
 * replace this section and leave the others alone.
 */
final readonly class Section
{
    /**
     * @param array<string, mixed> $props
     */
    public function __construct(
        public string $id,
        public string $component,
        public array $props,
    ) {
    }

    /**
     * @param array<string, mixed> $props
     */
    public function withProps(array $props): self
    {
        return new self($this->id, $this->component, $props);
    }

    /**
     * @return array{id: string, component: string, props: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'component' => $this->component, 'props' => $this->props];
    }
}
