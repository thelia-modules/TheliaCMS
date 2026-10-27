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

namespace TheliaCMS\Evaluation;

/**
 * Compares the runs of one brief at the three levels of
 * `docs/plans/deterministic-skills.md`.
 *
 * - **Structure**: the components of the sections and their ids, in order.
 * - **Shape**: per section, how many items each list holds, the heading
 *   levels and links of its markup, which props are set, and every address
 *   or id it points at. Two runs with the same shape show the same blocks,
 *   linked to the same pages and products, with other words.
 * - **Text**: the length of each section, and the figures it writes. Words
 *   are not compared: they are free to change, the facts are not.
 *
 * No API is called: the runs are read from what the generator printed.
 */
final readonly class RunComparison
{
    /**
     * @param list<Run> $runs
     */
    public function compare(string $brief, array $runs): BriefReport
    {
        $structures = [];
        $shapes = [];

        foreach ($runs as $run) {
            $structure = self::structure($run);
            $structures[$structure] = ($structures[$structure] ?? 0) + 1;
            $shape = self::shape($run);
            $shapes[$shape] = ($shapes[$shape] ?? 0) + 1;
        }

        arsort($structures);
        arsort($shapes);

        $commonStructure = array_key_first($structures);
        $alike = array_values(array_filter($runs, static fn (Run $run): bool => self::structure($run) === $commonStructure));

        $costs = array_values(array_filter(array_map(static fn (Run $run): ?float => $run->costUsd, $runs), static fn (?float $cost): bool => null !== $cost));
        $turns = array_values(array_filter(array_map(static fn (Run $run): ?int => $run->turns, $runs), static fn (?int $turn): bool => null !== $turn));

        return new BriefReport(
            brief: $brief,
            runs: \count($runs),
            structures: $structures,
            shapes: $shapes,
            lengthSpread: self::lengthSpread($alike),
            unsteadyFacts: self::unsteadyFacts($runs),
            averageCostUsd: [] === $costs ? null : array_sum($costs) / \count($costs),
            averageTurns: [] === $turns ? null : array_sum($turns) / \count($turns),
        );
    }

    /**
     * `component#id` of every section, in order, on one line.
     */
    public static function structure(Run $run): string
    {
        return implode(' > ', array_map(
            static fn (array $section): string => \sprintf('%s#%s', (string) ($section['component'] ?? '?'), (string) ($section['id'] ?? '?')),
            $run->sections(),
        ));
    }

    /**
     * The shape of every section, in order, as one JSON line.
     */
    public static function shape(Run $run): string
    {
        $shape = array_map(
            static fn (array $section): array => [
                'component' => $section['component'] ?? null,
                'props' => self::shapeOf($section['props'] ?? []),
            ],
            $run->sections(),
        );

        return json_encode($shape, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /**
     * What a value looks like, without its words: a list by its length and
     * the shape of its items, markup by its headings, links and list items,
     * an address or a number as it is, any other text as set or empty.
     */
    private static function shapeOf(mixed $value): mixed
    {
        if (\is_array($value)) {
            if (array_is_list($value)) {
                return ['items' => \count($value), 'each' => array_map(self::shapeOf(...), $value)];
            }

            ksort($value);

            return array_map(self::shapeOf(...), $value);
        }

        if (\is_int($value) || \is_float($value) || \is_bool($value) || null === $value) {
            return $value;
        }

        $text = trim((string) $value);

        if ('' === $text) {
            return 'empty';
        }

        if (self::isAddress($text)) {
            return $text;
        }

        if ($text !== strip_tags($text)) {
            preg_match_all('/<h([1-6])\b/i', $text, $headings);
            preg_match_all('/<a\b[^>]*href="([^"]*)"/i', $text, $links);

            return [
                'headings' => implode(',', $headings[1]),
                'links' => $links[1],
                'list_items' => preg_match_all('/<li\b/i', $text),
            ];
        }

        return 'text';
    }

    private static function isAddress(string $text): bool
    {
        return 1 === preg_match('#^(/|https?://|tel:|mailto:)\S*$#', $text);
    }

    /**
     * For runs of the same structure, how much the length of each section
     * varies: `(longest - shortest) / longest`, keyed `position#id`.
     *
     * @param list<Run> $runs
     *
     * @return array<string, float>
     */
    private static function lengthSpread(array $runs): array
    {
        $lengths = [];

        foreach ($runs as $run) {
            foreach ($run->sections() as $position => $section) {
                $key = \sprintf('%d#%s', $position + 1, (string) ($section['id'] ?? '?'));
                $lengths[$key][] = mb_strlen(self::textOf($section['props'] ?? []));
            }
        }

        $spread = [];

        foreach ($lengths as $key => $values) {
            $longest = max($values);
            $spread[$key] = 0 === $longest ? 0.0 : round(($longest - min($values)) / $longest, 2);
        }

        return $spread;
    }

    /**
     * The figures written by some runs and not by all: a fact the generator
     * should either always write or never.
     *
     * @param list<Run> $runs
     *
     * @return list<string>
     */
    private static function unsteadyFacts(array $runs): array
    {
        if ([] === $runs) {
            return [];
        }

        $perRun = array_map(static function (Run $run): array {
            preg_match_all('/\d+(?:[.,]\d+)?/u', self::textOf(array_map(static fn (array $section): mixed => $section['props'] ?? [], $run->sections())), $matches);

            return array_values(array_unique($matches[0]));
        }, $runs);

        $everywhere = array_values(array_intersect(...$perRun));
        $anywhere = array_values(array_unique(array_merge(...$perRun)));
        $unsteady = array_values(array_diff($anywhere, $everywhere));
        sort($unsteady);

        return $unsteady;
    }

    /**
     * The words of a value: every text but addresses, markup removed.
     */
    private static function textOf(mixed $value): string
    {
        if (\is_array($value)) {
            return implode(' ', array_map(self::textOf(...), $value));
        }

        if (!\is_string($value) || self::isAddress(trim($value))) {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), \ENT_QUOTES | \ENT_HTML5)));
    }
}
