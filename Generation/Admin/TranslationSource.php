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

namespace TheliaCMS\Generation\Admin;

use TheliaCMS\Content\Document;

/**
 * The draft a language is written from, with the fingerprint of it as it
 * was read: what the translation is later compared with.
 */
final readonly class TranslationSource
{
    public function __construct(
        public Document $document,
        public string $fingerprint,
    ) {
    }
}
