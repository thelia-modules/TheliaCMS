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
use TheliaCMS\Model\CmsFormField;

/**
 * A line of the field list of a form.
 */
final readonly class FieldRow
{
    public function __construct(
        public CmsFormField $field,
        public FieldType $type,
        public bool $canMoveUp,
        public bool $canMoveDown,
        public bool $isTranslated = true,
    ) {
    }
}
