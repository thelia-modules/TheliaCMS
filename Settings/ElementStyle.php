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
 * The style choices made for one element of the site: a heading level, the
 * paragraphs, the links or the buttons.
 *
 * Every property is optional — unset means the theme keeps its say — and every
 * value is checked against the shape of the property on the way in. These
 * strings end up written into a stylesheet served to visitors: what does not
 * match is dropped here, never quoted or escaped later.
 */
final readonly class ElementStyle
{
    private const string SIZE = '/^\d+(\.\d+)?(px|rem|em|%)$/';
    private const string SPACING = '/^-?\d+(\.\d+)?(px|rem|em)$/';
    private const string LINE_HEIGHT = '/^\d+(\.\d+)?(px|rem|em|%)?$/';
    private const string COLOR = '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';
    private const array WEIGHTS = ['100', '200', '300', '400', '500', '600', '700', '800', '900'];
    private const array TRANSFORMS = ['none', 'uppercase', 'lowercase', 'capitalize'];
    private const array UNDERLINES = ['underline', 'none'];

    private function __construct(
        public ?string $font,
        public ?string $size,
        public ?string $weight,
        public ?string $lineHeight,
        public ?string $letterSpacing,
        public ?string $transform,
        public ?string $color,
        public ?string $hoverColor,
        public ?string $underline,
        public ?string $background,
        public ?string $radius,
    ) {
    }

    /**
     * Reads a stored or submitted set of values, keeping what is well-formed
     * and dropping the rest.
     *
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values, FontStacks $stacks): self
    {
        return new self(
            font: self::font($values['font'] ?? null, $stacks),
            size: self::matching($values['size'] ?? null, self::SIZE),
            weight: self::oneOf($values['weight'] ?? null, self::WEIGHTS),
            lineHeight: self::matching($values['lineHeight'] ?? null, self::LINE_HEIGHT),
            letterSpacing: self::matching($values['letterSpacing'] ?? null, self::SPACING),
            transform: self::oneOf($values['transform'] ?? null, self::TRANSFORMS),
            color: self::matching($values['color'] ?? null, self::COLOR),
            hoverColor: self::matching($values['hoverColor'] ?? null, self::COLOR),
            underline: self::oneOf($values['underline'] ?? null, self::UNDERLINES),
            background: self::matching($values['background'] ?? null, self::COLOR),
            radius: self::matching($values['radius'] ?? null, self::SIZE),
        );
    }

    public static function none(): self
    {
        return new self(null, null, null, null, null, null, null, null, null, null, null);
    }

    /**
     * @return array<string, string> only what is set, ready to store
     */
    public function toArray(): array
    {
        return array_filter([
            'font' => $this->font,
            'size' => $this->size,
            'weight' => $this->weight,
            'lineHeight' => $this->lineHeight,
            'letterSpacing' => $this->letterSpacing,
            'transform' => $this->transform,
            'color' => $this->color,
            'hoverColor' => $this->hoverColor,
            'underline' => $this->underline,
            'background' => $this->background,
            'radius' => $this->radius,
        ], static fn (?string $value): bool => null !== $value);
    }

    public function isEmpty(): bool
    {
        return [] === $this->toArray();
    }

    /**
     * A font choice names either a stack of installed faces or an uploaded
     * file: `stack:garamond`, `file:MaPolice.woff2`.
     */
    private static function font(mixed $value, FontStacks $stacks): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        if (str_starts_with($value, 'stack:') && $stacks->has(substr($value, 6))) {
            return $value;
        }

        if (str_starts_with($value, 'file:')) {
            $file = substr($value, 5);

            // A file name, never a path, and only the format @font-face will
            // be written for.
            if ($file === basename($file) && str_ends_with(strtolower($file), '.woff2')) {
                return $value;
            }
        }

        return null;
    }

    private static function matching(mixed $value, string $pattern): ?string
    {
        return \is_string($value) && 1 === preg_match($pattern, trim($value)) ? trim($value) : null;
    }

    /** @param list<string> $allowed */
    private static function oneOf(mixed $value, array $allowed): ?string
    {
        return \is_string($value) && \in_array($value, $allowed, true) ? $value : null;
    }
}
