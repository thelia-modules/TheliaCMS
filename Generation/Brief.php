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

namespace TheliaCMS\Generation;

/**
 * What an editor asks for: the page in their own words, and what they know
 * about tone and audience when they care to say it.
 */
final readonly class Brief
{
    public function __construct(
        public string $text,
        public ?string $tone = null,
        public ?string $audience = null,
    ) {
        if ('' === trim($text)) {
            throw new \InvalidArgumentException('A brief says something.');
        }
    }
}
