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

use TheliaCMS\Model\CmsForm;

/**
 * A line of the form list: the record, and the two numbers that say whether the
 * form is finished and whether anyone is using it.
 */
final readonly class FormRow
{
    public function __construct(
        public CmsForm $form,
        public int $fieldCount,
        public int $submissionCount,
    ) {
    }
}
