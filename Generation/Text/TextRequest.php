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

namespace TheliaCMS\Generation\Text;

use TheliaCMS\Generation\GenerationRequest;

/**
 * A text asked for one rich-text field: the brief and the object as the
 * generator request carries them, the field, and what it already holds, to be
 * rewritten or completed as the brief says.
 */
final readonly class TextRequest
{
    public function __construct(
        public GenerationRequest $request,
        public TextField $field,
        public string $currentHtml = '',
    ) {
    }
}
