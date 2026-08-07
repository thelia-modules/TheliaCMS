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

namespace TheliaCMS\Form;

/**
 * The settings of a field that are not text: how tall a textarea is, and
 * whatever a later field type needs.
 *
 * Stored as JSON in one column rather than as a column per setting, because
 * these belong to one type each and a column would be empty for the eight
 * others. Anything unknown is dropped on the way in — the column is written by
 * the back office today and by an import tomorrow.
 */
final readonly class FieldOptions
{
    private const int MIN_ROWS = 2;

    private const int MAX_ROWS = 20;

    public function __construct(
        public int $rows = 4,
    ) {
    }

    public static function decode(?string $json): self
    {
        $decoded = json_decode((string) $json, true);

        if (!\is_array($decoded)) {
            return new self();
        }

        $rows = $decoded['rows'] ?? null;

        return new self(
            rows: is_numeric($rows) ? max(self::MIN_ROWS, min(self::MAX_ROWS, (int) $rows)) : 4,
        );
    }

    public function encode(): string
    {
        return json_encode(['rows' => $this->rows], \JSON_THROW_ON_ERROR);
    }
}
