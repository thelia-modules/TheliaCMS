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

namespace TheliaCMS\Form\Admin;

use TheliaCMS\Form\FieldType;

/**
 * What the field form carries, once read off the request.
 */
final readonly class FieldData
{
    public function __construct(
        public FieldType $type,
        public string $name,
        public string $label,
        public bool $required,
        public string $placeholder,
        public string $help,
        public string $choices,
        public int $rows,
    ) {
    }
}
