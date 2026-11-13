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

namespace TheliaCMS\Generation\Component;

use TheliaCMS\Content\PickedRecord;

/**
 * A product, a category, a folder, a form or a menu of the shop, as the
 * generator of components is told of it: what a `reference` of its sample may
 * name.
 */
final readonly class SampleRecord
{
    private const int NAME_LENGTH = 80;

    public function __construct(
        public PickedRecord $record,
        public int $id,
        public string $name,
    ) {
    }

    /**
     * One line of the user message. The name is written by whoever filled the
     * shop: one line, cut short.
     */
    public function line(): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $this->name));

        if (mb_strlen($name) > self::NAME_LENGTH) {
            $name = rtrim(mb_substr($name, 0, self::NAME_LENGTH - 1)).'…';
        }

        return \sprintf('- %s %d: %s', $this->record->value, $this->id, '' === $name ? 'no name' : $name);
    }
}
