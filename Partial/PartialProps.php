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

use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\TheliaCMS;

/**
 * Turns the settings stored in a page into the values a partial template is
 * allowed to receive.
 *
 * The props come out of a `data-props` attribute, which is to say out of the
 * page — hand-edited, imported, or written by an editor who found the HTML
 * panel. Nothing is passed through: a key the partial does not declare is
 * dropped, and a declared one is coerced to its type and kept inside its
 * bounds.
 */
final readonly class PartialProps
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, mixed> $raw settings as they were stored in the page
     *
     * @return array<string, string|int|bool|list<int>|null>
     *
     * @throws MissingPartialPropException when a required setting has no usable value
     */
    public function validate(array $raw, PartialDefinitionInterface $definition): array
    {
        $values = [];

        foreach ($definition->props() as $prop) {
            $value = $this->coerce($prop, $raw[$prop->name] ?? null);

            if ((null === $value || [] === $value) && $prop->required) {
                // Read by an editor in the canvas, so it names the setting the
                // way the settings panel does rather than by its key.
                throw new MissingPartialPropException($this->translator->trans(
                    'Choose a value for "%setting%" to show this block.',
                    ['%setting%' => $prop->label],
                    TheliaCMS::DOMAIN_NAME,
                ));
            }

            $values[$prop->name] = $value;
        }

        return $values;
    }

    /**
     * @return string|int|bool|list<int>|null
     */
    private function coerce(PartialProp $prop, mixed $value): string|int|bool|array|null
    {
        if (PartialPropType::References === $prop->type) {
            return $this->references($prop, $value);
        }

        if (\is_array($value) || \is_object($value)) {
            return $this->fallback($prop);
        }

        return match ($prop->type) {
            PartialPropType::Text => $this->text($prop, $value),
            PartialPropType::Integer => $this->integer($prop, $value),
            PartialPropType::Boolean => $this->boolean($prop, $value),
            PartialPropType::Choice => $this->choice($prop, $value),
            PartialPropType::Reference => $this->reference($prop, $value),
        };
    }

    /**
     * The ids of the records, in the order they were picked, each one once,
     * cut at the most the partial takes. A row is `{<itemName>: id}` as the
     * document stores it, or a bare id; anything that is no id is dropped.
     *
     * @return list<int>
     */
    private function references(PartialProp $prop, mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $row) {
            $candidate = \is_array($row) ? ($row[$prop->itemName ?? 'id'] ?? null) : $row;

            if (!is_numeric($candidate) || (int) $candidate <= 0 || (float) $candidate !== (float) (int) $candidate) {
                continue;
            }

            $id = (int) $candidate;

            if (!\in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return \array_slice($ids, 0, $prop->max ?? 12);
    }

    private function text(PartialProp $prop, mixed $value): ?string
    {
        if (null === $value) {
            return $this->fallback($prop);
        }

        $text = trim((string) $value);

        if ('' === $text) {
            return $this->fallback($prop);
        }

        // Templates escape what they print, so the cut is about storage size,
        // not about safety.
        return null !== $prop->max ? mb_substr($text, 0, $prop->max) : $text;
    }

    private function integer(PartialProp $prop, mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return $this->fallback($prop);
        }

        $number = (int) $value;

        if (null !== $prop->min) {
            $number = max($prop->min, $number);
        }

        if (null !== $prop->max) {
            $number = min($prop->max, $number);
        }

        return $number;
    }

    private function boolean(PartialProp $prop, mixed $value): bool
    {
        if (null === $value) {
            return (bool) $prop->default;
        }

        return filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE) ?? (bool) $prop->default;
    }

    private function choice(PartialProp $prop, mixed $value): ?string
    {
        $choice = null === $value ? null : (string) $value;

        return \array_key_exists((string) $choice, $prop->choices) ? $choice : $this->fallback($prop);
    }

    /**
     * A reference is the primary key of a record. Zero and negatives are not
     * ids, and neither is "12 OR 1=1": anything that is not a positive integer
     * is no reference at all.
     */
    private function reference(PartialProp $prop, mixed $value): ?int
    {
        if (!is_numeric($value) || (int) $value <= 0 || (float) $value !== (float) (int) $value) {
            return null === $prop->default ? null : (int) $prop->default;
        }

        return (int) $value;
    }

    private function fallback(PartialProp $prop): string|int|bool|null
    {
        return $prop->default;
    }
}
