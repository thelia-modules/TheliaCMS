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
 * The list of answers a drop-down or a set of radio buttons offers, written one
 * per line in the back office.
 *
 * The choices are content, translated like the rest: a form asking "Subject" in
 * French and in English offers sentences written in each. Nothing links the two
 * lists together, and nothing has to — a submission stores the answer the
 * visitor actually read, the same way a consent stores the sentence it was
 * given for.
 */
final readonly class FieldChoices
{
    /** Beyond this a drop-down is unusable, and the limit stops a paste accident. */
    public const int MAX = 100;

    private const int MAX_LENGTH = 255;

    /**
     * @return list<string>
     */
    public static function parse(?string $text): array
    {
        $lines = preg_split('/\R/', (string) $text) ?: [];
        $choices = [];

        foreach ($lines as $line) {
            $choice = trim($line);

            if ('' === $choice) {
                continue;
            }

            $choice = mb_substr($choice, 0, self::MAX_LENGTH);

            // A list offering the same answer twice sends back an ambiguous
            // value, and reads as a mistake to whoever fills the form in.
            if (!\in_array($choice, $choices, true)) {
                $choices[] = $choice;
            }

            if (\count($choices) >= self::MAX) {
                break;
            }
        }

        return $choices;
    }

    /**
     * @param list<string> $choices
     */
    public static function toText(array $choices): string
    {
        return implode("\n", $choices);
    }
}
