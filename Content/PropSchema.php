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
 * The props of a section, and what to do with the values it is handed.
 *
 * Normalising is strict where it costs nothing to be — a wrong type, a value
 * out of its range, an unknown choice are refused rather than coerced — and
 * forgiving where the author cannot know better: an unknown prop is dropped, a
 * missing one gets its default. What comes out is exactly what the template is
 * handed, so the template never checks a value.
 */
final readonly class PropSchema
{
    /**
     * @param list<Prop> $props
     */
    public function __construct(
        public array $props,
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     *
     * @return array<string, mixed>
     *
     * @throws InvalidDocumentException listing every problem found
     */
    public function normalize(array $raw): array
    {
        $values = [];
        $problems = [];

        foreach ($this->props as $prop) {
            try {
                $values[$prop->name] = $this->value($prop, \array_key_exists($prop->name, $raw) ? $raw[$prop->name] : null);
            } catch (InvalidDocumentException $exception) {
                foreach ($exception->problems as $problem) {
                    $problems[] = $problem;
                }
            }
        }

        if ([] !== $problems) {
            throw InvalidDocumentException::because($problems);
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function toJsonSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->props as $prop) {
            $properties[$prop->name] = $prop->toJsonSchema();

            if ($prop->required) {
                $required[] = $prop->name;
            }
        }

        return [
            'type' => 'object',
            'properties' => (object) $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];
    }

    /**
     * @throws InvalidDocumentException
     */
    private function value(Prop $prop, mixed $value): mixed
    {
        if (null === $value || '' === $value) {
            if ($prop->required) {
                throw InvalidDocumentException::because([\sprintf('"%s" is required.', $prop->name)]);
            }

            return \in_array($prop->type, [PropType::Image, PropType::Reference], true) ? null : $prop->default;
        }

        return match ($prop->type) {
            PropType::Text => $this->text($prop, $value),
            PropType::Html => $this->html($prop, $value),
            PropType::Integer => $this->integer($prop, $value),
            PropType::Boolean => $this->boolean($prop, $value),
            PropType::Choice => $this->choice($prop, $value),
            PropType::Image, PropType::Reference => $this->image($prop, $value),
            PropType::Url => $this->url($prop, $value),
            PropType::Items => $this->items($prop, $value),
            PropType::Json => $this->json($prop, $value),
        };
    }

    private function json(Prop $prop, mixed $value): mixed
    {
        if (!\is_array($value) && !\is_scalar($value)) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be a list, an object or a scalar.', $prop->name)]);
        }

        return $value;
    }

    private function text(Prop $prop, mixed $value): string
    {
        if (!\is_scalar($value)) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be a string.', $prop->name)]);
        }

        $text = trim((string) $value);

        if (null !== $prop->max && mb_strlen($text) > $prop->max) {
            throw InvalidDocumentException::because([\sprintf('"%s" is longer than %d characters.', $prop->name, $prop->max)]);
        }

        if ('' === $text && $prop->required) {
            throw InvalidDocumentException::because([\sprintf('"%s" is required.', $prop->name)]);
        }

        return $text;
    }

    private function html(Prop $prop, mixed $value): string
    {
        if (!\is_string($value)) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be a string of markup.', $prop->name)]);
        }

        return $value;
    }

    private function integer(Prop $prop, mixed $value): int
    {
        if (!\is_int($value) && !(\is_string($value) && 1 === preg_match('/^-?\d+$/', $value))) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be a whole number.', $prop->name)]);
        }

        $integer = (int) $value;

        if ((null !== $prop->min && $integer < $prop->min) || (null !== $prop->max && $integer > $prop->max)) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be between %d and %d.', $prop->name, $prop->min ?? \PHP_INT_MIN, $prop->max ?? \PHP_INT_MAX)]);
        }

        return $integer;
    }

    private function boolean(Prop $prop, mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
            return \in_array($value, [1, '1', 'true'], true);
        }

        throw InvalidDocumentException::because([\sprintf('"%s" must be true or false.', $prop->name)]);
    }

    private function choice(Prop $prop, mixed $value): string
    {
        if (!\is_scalar($value) || !\array_key_exists((string) $value, $prop->choices)) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be one of %s.', $prop->name, implode(', ', array_keys($prop->choices)))]);
        }

        return (string) $value;
    }

    /**
     * An image id, or the id of a referenced item: the same shape, a positive
     * integer or nothing.
     */
    private function image(Prop $prop, mixed $value): ?int
    {
        $what = PropType::Image === $prop->type ? 'the id of an image of the media library' : 'the id of the item it refers to';

        if (!\is_int($value) && !(\is_string($value) && 1 === preg_match('/^\d+$/', $value))) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be %s.', $prop->name, $what)]);
        }

        $id = (int) $value;

        // Zero is "no picture yet": what a sample holds, and what a section
        // shows as a placeholder until an image is chosen.
        if (0 === $id) {
            if ($prop->required) {
                throw InvalidDocumentException::because([\sprintf('"%s" is required.', $prop->name)]);
            }

            return null;
        }

        if ($id < 0) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be %s.', $prop->name, $what)]);
        }

        return $id;
    }

    private function url(Prop $prop, mixed $value): string
    {
        if (!\is_string($value)) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be an address.', $prop->name)]);
        }

        $url = trim($value);

        // A path of the site, an absolute web address, a mail or phone link.
        // Anything else — `javascript:` first of all — is not an address a page
        // may send a visitor to.
        if (1 !== preg_match('#^(/(?!/)|\#|https?://|mailto:|tel:)#i', $url)) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be a path of the site or an absolute http(s), mailto or tel address.', $prop->name)]);
        }

        if (null !== $prop->max && \strlen($url) > $prop->max) {
            throw InvalidDocumentException::because([\sprintf('"%s" is longer than %d characters.', $prop->name, $prop->max)]);
        }

        return $url;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(Prop $prop, mixed $value): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidDocumentException::because([\sprintf('"%s" must be a list.', $prop->name)]);
        }

        $count = \count($value);

        if ((null !== $prop->minItems && $count < $prop->minItems) || (null !== $prop->maxItems && $count > $prop->maxItems)) {
            throw InvalidDocumentException::because([\sprintf('"%s" must hold between %d and %d items.', $prop->name, $prop->minItems ?? 0, $prop->maxItems ?? 12)]);
        }

        $schema = new self($prop->items);
        $items = [];
        $problems = [];

        foreach ($value as $index => $item) {
            if (!\is_array($item)) {
                $problems[] = \sprintf('"%s" item %d is not an object.', $prop->name, $index + 1);

                continue;
            }

            try {
                $items[] = $schema->normalize($item);
            } catch (InvalidDocumentException $exception) {
                foreach ($exception->problems as $problem) {
                    $problems[] = \sprintf('"%s" item %d: %s', $prop->name, $index + 1, $problem);
                }
            }
        }

        if ([] !== $problems) {
            throw InvalidDocumentException::because($problems);
        }

        return $items;
    }
}
