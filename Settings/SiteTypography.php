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

namespace TheliaCMS\Settings;

/**
 * The global styles of the site, one entry per element an editor can settle:
 * the six heading levels, the paragraphs, the links and the buttons.
 */
final readonly class SiteTypography
{
    public const array ELEMENTS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'button'];

    /**
     * @param array<string, ElementStyle> $elements keyed by element, complete
     */
    private function __construct(
        private array $elements,
    ) {
    }

    /**
     * @param array<string, mixed> $values as stored or as submitted
     */
    public static function fromArray(array $values, FontStacks $stacks): self
    {
        $elements = [];

        foreach (self::ELEMENTS as $element) {
            $raw = $values[$element] ?? null;
            $elements[$element] = \is_array($raw) ? ElementStyle::fromArray($raw, $stacks) : ElementStyle::none();
        }

        return new self($elements);
    }

    public function element(string $element): ElementStyle
    {
        return $this->elements[$element] ?? ElementStyle::none();
    }

    /**
     * @return array<string, array<string, string>> only the elements with choices in them
     */
    public function toArray(): array
    {
        $values = [];

        foreach ($this->elements as $element => $style) {
            if (!$style->isEmpty()) {
                $values[$element] = $style->toArray();
            }
        }

        return $values;
    }

    public function isEmpty(): bool
    {
        return [] === $this->toArray();
    }

    /**
     * The uploaded font files these choices rely on, deduplicated.
     *
     * @return list<string>
     */
    public function fontFiles(): array
    {
        $files = [];

        foreach ($this->elements as $style) {
            if (null !== $style->font && str_starts_with($style->font, 'file:')) {
                $files[] = substr($style->font, 5);
            }
        }

        return array_values(array_unique($files));
    }
}
