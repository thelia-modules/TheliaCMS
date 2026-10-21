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

namespace TheliaCMS\Page;

use TheliaCMS\Page\Field\FieldDefinitions;
use TheliaCMS\Page\Field\FieldKind;

/**
 * What a list of the pages of a type asks for: an order, the values a page
 * must carry, how many. Read from a template, so every option is optional
 * and an option that makes no sense is ignored rather than fatal.
 *
 *   cms_pages_of_type('recipe', {sort: 'cooking_time', filter: {vegetarian: true, servings: {min: 4}}, limit: 6})
 */
final readonly class PageListQuery
{
    public const string SORT_RECENT = 'recent';
    public const string SORT_OLDEST = 'oldest';
    public const string SORT_TITLE = 'title';
    public const string SORT_POSITION = 'position';

    public const int MAX_LIMIT = 100;

    /**
     * @param string               $sort    one of the SORT_* constants, or the code of a field
     * @param array<string, mixed> $filters by field code: a value the field must hold, or `{min, max}` for a number or a date
     */
    public function __construct(
        public string $sort = self::SORT_RECENT,
        public bool $descending = false,
        public array $filters = [],
        public ?int $limit = null,
        public int $offset = 0,
    ) {
    }

    /**
     * @param array<mixed> $options sort, direction (asc or desc), filter, limit, offset
     */
    public static function fromArray(array $options): self
    {
        $sort = \is_string($options['sort'] ?? null) && '' !== trim($options['sort']) ? trim($options['sort']) : self::SORT_RECENT;
        $limit = is_numeric($options['limit'] ?? null) ? max(1, min(self::MAX_LIMIT, (int) $options['limit'])) : null;

        return new self(
            sort: $sort,
            descending: 'desc' === strtolower(\is_string($options['direction'] ?? null) ? $options['direction'] : ''),
            filters: \is_array($options['filter'] ?? null) ? $options['filter'] : [],
            limit: $limit,
            offset: is_numeric($options['offset'] ?? null) ? max(0, (int) $options['offset']) : 0,
        );
    }

    /**
     * Whether the values of a page pass every filter. A filter on a field the
     * type does not define lets nothing through: it names something the
     * pages do not have.
     *
     * @param array<string, int|float|string|bool|null> $values
     */
    public function matches(array $values, FieldDefinitions $definitions): bool
    {
        foreach ($this->filters as $code => $expected) {
            $field = $definitions->find((string) $code);

            if (null === $field) {
                return false;
            }

            $value = $values[$field->code] ?? null;

            if (\is_array($expected)) {
                if (null === $value || !self::within($value, $expected, $field->kind)) {
                    return false;
                }

                continue;
            }

            try {
                $wanted = $field->normalize($expected);
            } catch (\InvalidArgumentException) {
                return false;
            }

            if (FieldKind::Boolean === $field->kind && false === $wanted && null === $value) {
                continue;
            }

            if ($value !== $wanted) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compares two rows of a list. Pages without a value for the field of
     * the order go last, whichever way it runs.
     *
     * @param array{title: string, position: int, published_at: \DateTimeInterface|null, values: array<string, mixed>} $a
     * @param array{title: string, position: int, published_at: \DateTimeInterface|null, values: array<string, mixed>} $b
     */
    public function compare(array $a, array $b, FieldDefinitions $definitions): int
    {
        $result = match ($this->sort) {
            self::SORT_RECENT => $b['published_at'] <=> $a['published_at'],
            self::SORT_OLDEST => $a['published_at'] <=> $b['published_at'],
            self::SORT_TITLE => self::text($a['title'], $b['title']),
            self::SORT_POSITION => $a['position'] <=> $b['position'],
            default => null,
        };

        if (null !== $result) {
            $result = \in_array($this->sort, [self::SORT_TITLE, self::SORT_POSITION], true) && $this->descending ? -$result : $result;

            return 0 !== $result ? $result : self::text($a['title'], $b['title']);
        }

        $field = $definitions->find($this->sort);

        if (null === $field) {
            return $b['published_at'] <=> $a['published_at'];
        }

        $left = $a['values'][$field->code] ?? null;
        $right = $b['values'][$field->code] ?? null;

        if (null === $left || null === $right) {
            return (null === $left) <=> (null === $right) ?: self::text($a['title'], $b['title']);
        }

        $result = \is_string($left) && \is_string($right) ? self::text($left, $right) : $left <=> $right;
        $result = $this->descending ? -$result : $result;

        return 0 !== $result ? $result : self::text($a['title'], $b['title']);
    }

    /**
     * @param array<mixed> $range
     */
    private static function within(int|float|string|bool $value, array $range, FieldKind $kind): bool
    {
        if (!$kind->isNumber() && FieldKind::Date !== $kind) {
            return false;
        }

        foreach (['min' => 1, 'max' => -1] as $bound => $sign) {
            $limit = $range[$bound] ?? null;

            if (null === $limit) {
                continue;
            }

            if (!\is_scalar($limit)) {
                return false;
            }

            $comparison = $kind->isNumber() ? $value <=> (float) $limit : strcmp((string) $value, (string) $limit);

            if ($comparison * $sign < 0) {
                return false;
            }
        }

        return true;
    }

    private static function text(string $a, string $b): int
    {
        return strnatcasecmp($a, $b);
    }
}
