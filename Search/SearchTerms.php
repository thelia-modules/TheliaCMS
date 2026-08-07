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

namespace TheliaCMS\Search;

/**
 * What a visitor typed, turned into something MySQL boolean mode accepts.
 *
 * Boolean mode reads `+`, `-`, `*`, `<`, `>`, `(` and `"` as operators, so a
 * search for "C++" or for a lone `-` is a syntax error rather than a query with
 * no results. The operators are dropped instead of escaped: there is no escape
 * character in boolean mode, and offering the syntax to visitors would let them
 * write queries nobody meant to allow.
 */
final readonly class SearchTerms
{
    /** Below this, MySQL's default `ft_min_word_len` finds nothing anyway. */
    public const int MIN_WORD_LENGTH = 3;

    /** Long enough for a real question, short enough not to be a payload. */
    public const int MAX_LENGTH = 120;

    private function __construct(
        public string $raw,
        /** @var list<string> */
        public array $words,
    ) {
    }

    public static function fromInput(?string $input): self
    {
        $raw = trim(mb_substr(preg_replace('#\s+#u', ' ', (string) $input) ?? '', 0, self::MAX_LENGTH));

        // Everything that is not a letter, a digit or a mark becomes a space:
        // that covers the boolean operators and the punctuation in one pass,
        // and keeps accented letters, which a French site is made of.
        $cleaned = preg_replace('#[^\p{L}\p{N}\p{M}]+#u', ' ', $raw) ?? '';

        $words = [];

        foreach (explode(' ', $cleaned) as $word) {
            if (mb_strlen($word) >= self::MIN_WORD_LENGTH) {
                $words[] = $word;
            }
        }

        // Repeating a word in the query only slows it down.
        return new self($raw, array_values(array_unique($words)));
    }

    public function isSearchable(): bool
    {
        return [] !== $this->words;
    }

    /**
     * The boolean-mode expression: every word required, and the last one
     * completed as a prefix so that "access" already matches "accessibility"
     * while the visitor is still typing.
     */
    public function toBooleanQuery(): string
    {
        if (!$this->isSearchable()) {
            return '';
        }

        $parts = [];
        $last = \count($this->words) - 1;

        foreach ($this->words as $index => $word) {
            $parts[] = $index === $last ? '+'.$word.'*' : '+'.$word;
        }

        return implode(' ', $parts);
    }
}
