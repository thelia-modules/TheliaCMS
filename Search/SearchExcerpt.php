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
 * The few words around the first match, so a result says why it is a result.
 *
 * A list of titles alone makes the visitor open pages to find out which one
 * they meant.
 */
final readonly class SearchExcerpt
{
    public const int LENGTH = 180;

    public function around(string $text, SearchTerms $terms): string
    {
        $text = trim(preg_replace('#\s+#u', ' ', $text) ?? $text);

        if ('' === $text) {
            return '';
        }

        $position = $this->firstMatch($text, $terms);

        if (null === $position) {
            return $this->clip($text, 0, false);
        }

        // Start a little before the match so the word is not glued to the
        // leading ellipsis, and cut back to a space so no word is halved.
        $start = max(0, $position - 40);

        if ($start > 0) {
            $space = mb_strpos($text, ' ', $start);
            $start = false === $space ? $start : $space + 1;
        }

        return $this->clip($text, $start, $start > 0);
    }

    private function firstMatch(string $text, SearchTerms $terms): ?int
    {
        $best = null;

        foreach ($terms->words as $word) {
            $position = mb_stripos($text, $word);

            if (false !== $position && (null === $best || $position < $best)) {
                $best = $position;
            }
        }

        return $best;
    }

    private function clip(string $text, int $start, bool $prefix): string
    {
        $slice = mb_substr($text, $start, self::LENGTH);
        $truncated = mb_strlen($text) > $start + self::LENGTH;

        if ($truncated) {
            $lastSpace = mb_strrpos($slice, ' ');
            $slice = false === $lastSpace ? $slice : mb_substr($slice, 0, $lastSpace);
        }

        return ($prefix ? '… ' : '').trim($slice).($truncated ? ' …' : '');
    }
}
