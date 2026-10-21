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

namespace TheliaCMS\Page\Field;

use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\TheliaCMS;

/**
 * A value as a visitor reads it, in the language of the page: "1 h 30 min",
 * "4,5 kg", "yes". A template that wants its own wording reads the raw value.
 */
final readonly class FieldFormatter
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function format(FieldDefinition $field, int|float|string|bool|null $value, string $locale): ?string
    {
        if (null === $value) {
            return null;
        }

        $text = match ($field->kind) {
            FieldKind::Integer => $this->number((int) $value, $locale, 0),
            FieldKind::Decimal => $this->number((float) $value, $locale, 2),
            FieldKind::Duration => $this->duration((int) $value, $locale),
            FieldKind::Boolean => $this->translator->trans(true === $value ? 'Yes' : 'No', [], TheliaCMS::DOMAIN_NAME, $locale),
            FieldKind::Date => $this->date((string) $value, $locale),
            default => (string) $value,
        };

        return null !== $field->unit && FieldKind::Duration !== $field->kind ? $text.' '.$field->unit : $text;
    }

    /**
     * The fields that hold a value, in the order of the type, each with its
     * label and its value written for a visitor.
     *
     * @param array<string, int|float|string|bool|null> $values
     *
     * @return list<array{code: string, label: string, value: int|float|string|bool, text: string}>
     */
    public function rows(FieldDefinitions $definitions, array $values, string $locale): array
    {
        $rows = [];

        foreach ($definitions as $field) {
            $value = $values[$field->code] ?? null;

            if (null === $value) {
                continue;
            }

            $rows[] = [
                'code' => $field->code,
                'label' => $field->label($locale),
                'value' => $value,
                'text' => (string) $this->format($field, $value, $locale),
            ];
        }

        return $rows;
    }

    private function number(int|float $value, string $locale, int $decimals): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        $text = $formatter->format($value);

        return false === $text ? (string) $value : $text;
    }

    private function duration(int $minutes, string $locale): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if (0 === $hours) {
            return $this->translator->trans('%minutes% min', ['%minutes%' => $rest], TheliaCMS::DOMAIN_NAME, $locale);
        }

        if (0 === $rest) {
            return $this->translator->trans('%hours% h', ['%hours%' => $hours], TheliaCMS::DOMAIN_NAME, $locale);
        }

        return $this->translator->trans('%hours% h %minutes% min', ['%hours%' => $hours, '%minutes%' => $rest], TheliaCMS::DOMAIN_NAME, $locale);
    }

    private function date(string $value, string $locale): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (false === $date) {
            return $value;
        }

        $text = (new \IntlDateFormatter($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE, 'UTC'))->format($date);

        return false === $text ? $value : $text;
    }
}
