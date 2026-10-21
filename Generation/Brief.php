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

    /**
     * The web pages the brief cites, in the order it cites them: the model
     * may read them before it writes.
     *
     * @return list<string>
     */
    public function addresses(): array
    {
        preg_match_all('~https?://[^\s<>"\'`]+~i', $this->text, $matches);

        // An address ending a sentence carries its punctuation: it is not part of it.
        $addresses = array_map(static fn (string $address): string => rtrim($address, '.,;:!?)]}'), $matches[0]);

        return array_values(array_unique($addresses));
    }
}
