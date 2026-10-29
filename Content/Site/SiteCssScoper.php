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
 * `:scope` and `&` only start a selector: anywhere else, or before `~` or
 * `+`, they would name what is around the component, and are refused.
 *
 * Rules may sit in `@media`, `@supports` and `@container`. A `@keyframes`
 * is a name of the whole page, which two components would fight over: it is
 * named after the component, and so is every `animation` of the stylesheet
 * that uses it.
 *
 *     @keyframes pan { … }    →  @keyframes cms-site--price-table--pan { … }
 *     animation: pan 40s;     →  animation: cms-site--price-table--pan 40s;
 *
 * An animation only moves for the visitors who did not ask for less motion:
 * a rule that sets one sits in `@media (prefers-reduced-motion:
 * no-preference)`. Any other at-rule is refused: `@import` and `@font-face`
 * load from elsewhere, `@property` is a name of the whole page too.
 */
final readonly class SiteCssScoper
{
    /** @var list<string> */
    private const array GROUPING_RULES = ['media', 'supports', 'container'];

    /**
     * The words of the `animation` property, which an animation of the same
     * name would be read as.
     *
     * @var list<string>
     */
    private const array ANIMATION_WORDS = ['none', 'initial', 'inherit', 'unset', 'revert', 'revert-layer', 'linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out', 'step-start', 'step-end', 'infinite', 'normal', 'reverse', 'alternate', 'alternate-reverse', 'forwards', 'backwards', 'both', 'running', 'paused', 'auto'];

    /** The group an animation may run in. */
    private const string MOTION_ALLOWED = '/prefers-reduced-motion\s*:\s*no-preference/i';

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
        $scoped = $this->rules($css, $offset, '.'.$class, $problems, false, $this->animations($css, $class), false);

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
     * @param list<string>          $problems
     * @param array<string, string> $animations the name each `@keyframes` of the stylesheet is given
     * @param bool                  $moving     whether the rules sit in a group that only applies when the visitor did not ask for less motion
     */
    private function rules(string $css, int &$offset, string $scope, array &$problems, bool $nested, array $animations, bool $moving): string
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

                if ('keyframes' === $rule) {
                    $out .= $this->keyframes(trim(substr($prelude, \strlen('@keyframes'))), $css, $offset, $animations, $problems);

                    continue;
                }

                if (!\in_array($rule, self::GROUPING_RULES, true)) {
                    $problems[] = \sprintf('The stylesheet uses @%s; a component may only group its rules in @media, @supports and @container, and name its animations in @keyframes.', $rule);
                    $this->skipBlock($css, $offset);

                    continue;
                }

                $inner = $this->rules($css, $offset, $scope, $problems, true, $animations, $moving || 1 === preg_match(self::MOTION_ALLOWED, $prelude));
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

            $body = trim($body);

            if (!$moving && 1 === preg_match('/(?:^|;)\s*animation(?:-name)?\s*:(?!\s*none\s*(?:;|$))/i', $body)) {
                $problems[] = \sprintf('The rule "%s" sets an animation outside of @media (prefers-reduced-motion: no-preference): a visitor whose system asks for less motion would still see it move.', $prelude);
            }

            $body = (string) preg_replace_callback(
                '/((?:^|;)\s*animation(?:-name)?\s*:)([^;]*)/i',
                static fn (array $declaration): string => $declaration[1].preg_replace_callback(
                    '/(?<![\w-])[a-z_][\w-]*/i',
                    static fn (array $word): string => $animations[$word[0]] ?? $word[0],
                    $declaration[2],
                ),
                $body,
            );

            $selectors = [];

            foreach ($this->selectors($prelude) as $selector) {
                $scoped = $this->scoped($selector, $scope);

                if (null === $scoped) {
                    $problems[] = \sprintf('The selector "%s" reaches past the component: `:scope` and `&` only start a selector, followed by a space or `>`.', $selector);

                    continue 2;
                }

                $selectors[] = $scoped;
            }

            $out .= implode(', ', $selectors).' {'.$body."}\n";
        }

        if ($nested) {
            $problems[] = 'The stylesheet leaves a group open.';
        }

        return $out;
    }

    /**
     * The name each `@keyframes` of the stylesheet is given: its own, after
     * the class of the component. A name that is not a plain one is left out,
     * and named as such where its block is read.
     *
     * @return array<string, string>
     */
    private function animations(string $css, string $class): array
    {
        preg_match_all('/@keyframes\s+([^\s{]+)/i', $css, $matches);
        $animations = [];

        foreach ($matches[1] as $name) {
            if (1 === preg_match('/^[a-z][a-z0-9-]{0,39}$/i', $name) && !\in_array(strtolower($name), self::ANIMATION_WORDS, true)) {
                $animations[$name] = $class.'--'.$name;
            }
        }

        return $animations;
    }

    /**
     * A `@keyframes` block from just after its brace, under the name the
     * component gives it: steps (`from`, `to`, a percentage), each with its
     * declarations, and nothing else.
     *
     * @param array<string, string> $animations
     * @param list<string>          $problems
     */
    private function keyframes(string $name, string $css, int &$offset, array $animations, array &$problems): string
    {
        if (!isset($animations[$name])) {
            $problems[] = \sprintf('The animation name "%s" is not a plain name: letters, digits and hyphens, starting with a letter, and none of the words of the `animation` property ("infinite", "linear"…).', $name);
            $this->skipBlock($css, $offset);

            return '';
        }

        $steps = '';

        while (true) {
            if (1 === preg_match('/\G\s*\}/', $css, $match, 0, $offset)) {
                $offset += \strlen($match[0]);

                return '@keyframes '.$animations[$name].' {'.$steps."}\n";
            }

            if (1 !== preg_match('/\G\s*([^{}]+)\{([^{}]*)\}/', $css, $match, 0, $offset)) {
                if ('' === trim(substr($css, $offset)) || !str_contains(substr($css, $offset), '}')) {
                    $problems[] = 'The stylesheet leaves a group open.';
                    $offset = \strlen($css);

                    return '';
                }

                $problems[] = \sprintf('The @keyframes "%s" holds something other than steps (`from`, `to`, a percentage), each with its declarations.', $name);
                $this->skipBlock($css, $offset);

                return '';
            }

            foreach (explode(',', $match[1]) as $step) {
                if (1 !== preg_match('/^\s*(?:from|to|(?:100|\d{1,2})(?:\.\d+)?%)\s*$/i', $step)) {
                    $problems[] = \sprintf('The @keyframes "%s" has the step "%s": a step is `from`, `to` or a percentage.', $name, trim($step));
                }
            }

            $steps .= trim($match[1]).' {'.trim($match[2])."}\n";
            $offset += \strlen($match[0]);
        }
    }

    /**
     * The selector under the class of the component; null when it would
     * reach past it. `:scope` or `&` may only start a selector, the element
     * itself or what is inside it: anywhere else (`:not(:scope)`), or before
     * a sibling combinator (`& ~ p`), it names what is around the component.
     */
    private function scoped(string $selector, string $scope): ?string
    {
        if (1 === preg_match('/^(?::scope|&)(.*)$/s', $selector, $match)) {
            $rest = $match[1];

            if (str_contains($rest, ':scope') || str_contains($rest, '&') || 1 === preg_match('/^\s*[~+]/', $rest)) {
                return null;
            }

            return $scope.$rest;
        }

        if (str_contains($selector, ':scope') || str_contains($selector, '&')) {
            return null;
        }

        return $scope.' '.$selector;
    }

    /**
     * The selectors of a list, split on the commas that are not inside
     * parentheses (`:is(a, b)`), brackets (`[title="a,b"]`) or quotes. An
     * escaped quote is not read as one: SiteComponentValidator refuses every
     * backslash in the stylesheet of a component, which this relies on.
     *
     * @return list<string>
     */
    private function selectors(string $prelude): array
    {
        $selectors = [];
        $depth = 0;
        $quote = null;
        $current = '';

        foreach (str_split($prelude) as $character) {
            if (null !== $quote) {
                if ($character === $quote) {
                    $quote = null;
                }
            } elseif ('"' === $character || "'" === $character) {
                $quote = $character;
            } elseif ('(' === $character || '[' === $character) {
                ++$depth;
            } elseif (')' === $character || ']' === $character) {
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
