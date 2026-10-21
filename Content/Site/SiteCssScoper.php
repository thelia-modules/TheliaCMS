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

namespace TheliaCMS\Content\Site;

/**
 * Keeps the stylesheet of a component of the site to the component: every
 * selector is put under the class the component is drawn inside, so a rule
 * written for it never reaches the rest of the page.
 *
 *     .plan { … }            →  .cms-site--price-table .plan { … }
 *     :scope { … }, & { … }  →  .cms-site--price-table { … }
 *
 * Rules may sit in `@media`, `@supports` and `@container`. Any other at-rule
 * is refused: `@import` and `@font-face` load from elsewhere, `@keyframes`
 * and `@property` are names of the whole page, which two components would
 * fight over.
 */
final readonly class SiteCssScoper
{
    /** @var list<string> */
    private const array GROUPING_RULES = ['media', 'supports', 'container'];

    public const int MAX_LENGTH = 20000;

    /**
     * @throws InvalidSiteComponentException when the stylesheet cannot be scoped
     */
    public function scope(string $css, string $class): string
    {
        if (mb_strlen($css) > self::MAX_LENGTH) {
            throw InvalidSiteComponentException::because([\sprintf('The stylesheet is %d characters at most.', self::MAX_LENGTH)]);
        }

        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $problems = [];
        $offset = 0;
        $scoped = $this->rules($css, $offset, '.'.$class, $problems, false);

        if ($offset < \strlen($css) && [] === $problems) {
            $problems[] = 'The stylesheet closes a brace it never opened.';
        }

        if ([] !== $problems) {
            throw InvalidSiteComponentException::because(array_values(array_unique($problems)));
        }

        return trim($scoped);
    }

    /**
     * Reads rules from `$offset` until the end of the stylesheet or the brace
     * that closes the group they are in.
     *
     * @param list<string> $problems
     */
    private function rules(string $css, int &$offset, string $scope, array &$problems, bool $nested): string
    {
        $out = '';
        $length = \strlen($css);

        while ($offset < $length) {
            // An at-rule without a block (`@import url(…);`, `@charset`) ends
            // at its semicolon: named, and stepped over.
            if (1 === preg_match('/\G\s*@([a-z-]+)[^{};]*;/i', $css, $statement, 0, $offset)) {
                $problems[] = \sprintf('The stylesheet uses @%s; a component may only group its rules in @media, @supports and @container.', strtolower($statement[1]));
                $offset += \strlen($statement[0]);

                continue;
            }

            $open = strpos($css, '{', $offset);
            $close = strpos($css, '}', $offset);

            if (false !== $close && (false === $open || $close < $open)) {
                if ('' !== trim(substr($css, $offset, $close - $offset))) {
                    $problems[] = 'The stylesheet holds a declaration outside of any rule.';
                }

                if ($nested) {
                    $offset = $close + 1;
                }

                return $out;
            }

            if (false === $open) {
                if ('' !== trim(substr($css, $offset))) {
                    $problems[] = 'The stylesheet ends with text outside of any rule.';
                }

                $offset = $length;

                return $out;
            }

            $prelude = trim(substr($css, $offset, $open - $offset));
            $offset = $open + 1;

            if (str_starts_with($prelude, '@')) {
                $rule = strtolower((string) preg_replace('/^@([a-z-]+).*$/s', '$1', $prelude));

                if (!\in_array($rule, self::GROUPING_RULES, true)) {
                    $problems[] = \sprintf('The stylesheet uses @%s; a component may only group its rules in @media, @supports and @container.', $rule);
                    $this->skipBlock($css, $offset);

                    continue;
                }

                $inner = $this->rules($css, $offset, $scope, $problems, true);
                $out .= $prelude.' {'.$inner."}\n";

                continue;
            }

            $end = strpos($css, '}', $offset);

            if (false === $end) {
                $problems[] = 'The stylesheet leaves a rule open.';
                $offset = $length;

                return $out;
            }

            $body = substr($css, $offset, $end - $offset);
            $offset = $end + 1;

            if (str_contains($body, '{')) {
                $problems[] = 'The stylesheet nests a rule in a rule; write each rule on its own.';

                continue;
            }

            if ('' === $prelude) {
                $problems[] = 'The stylesheet has a rule without a selector.';

                continue;
            }

            $out .= implode(', ', array_map(fn (string $selector): string => $this->scoped($selector, $scope), $this->selectors($prelude))).' {'.trim($body)."}\n";
        }

        if ($nested) {
            $problems[] = 'The stylesheet leaves a group open.';
        }

        return $out;
    }

    private function scoped(string $selector, string $scope): string
    {
        if (str_contains($selector, ':scope')) {
            return str_replace(':scope', $scope, $selector);
        }

        if (str_starts_with($selector, '&')) {
            return $scope.substr($selector, 1);
        }

        return $scope.' '.$selector;
    }

    /**
     * The selectors of a list, split on the commas that are not inside
     * parentheses (`:is(a, b)` is one).
     *
     * @return list<string>
     */
    private function selectors(string $prelude): array
    {
        $selectors = [];
        $depth = 0;
        $current = '';

        foreach (str_split($prelude) as $character) {
            if ('(' === $character) {
                ++$depth;
            } elseif (')' === $character) {
                --$depth;
            } elseif (',' === $character && 0 === $depth) {
                $selectors[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $character;
        }

        $selectors[] = trim($current);

        return array_values(array_filter($selectors, static fn (string $selector): bool => '' !== $selector));
    }

    private function skipBlock(string $css, int &$offset): void
    {
        $depth = 1;
        $length = \strlen($css);

        while ($offset < $length && $depth > 0) {
            $character = $css[$offset++];
            $depth += match ($character) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
        }
    }
}
