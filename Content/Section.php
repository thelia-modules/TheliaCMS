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
 * props, and how it stands out from the page. The id names the section
 * inside its document, so a later change can replace this section and leave
 * the others alone.
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
        public SectionAppearance $appearance = new SectionAppearance(),
    ) {
    }

    /**
     * @param array<string, mixed> $props
     */
    public function withProps(array $props): self
    {
        return new self($this->id, $this->component, $props, $this->appearance);
    }

    public function withAppearance(SectionAppearance $appearance): self
    {
        return new self($this->id, $this->component, $this->props, $appearance);
    }

    /**
     * The appearance is written only when it is set: a section drawn as its
     * component draws it reads as it did before appearances existed.
     *
     * @return array{id: string, component: string, props: array<string, mixed>, appearance?: array<string, string>}
     */
    public function toArray(): array
    {
        $array = ['id' => $this->id, 'component' => $this->component, 'props' => $this->props];

        if (!$this->appearance->isEmpty()) {
            $array['appearance'] = $this->appearance->toArray();
        }

        return $array;
    }
}
