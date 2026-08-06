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

namespace TheliaCMS\Builder;

use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\TheliaCMS;

/**
 * Checks the heading structure of a page before it goes online.
 *
 * Headings are how anyone using a screen reader navigates a page: two `h1`
 * give two competing titles, and jumping from `h2` to `h4` hides a level that
 * reader was counting on. Both are cheap to catch here and expensive to notice
 * later.
 */
final readonly class HeadingChecker
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<string> readable problems, empty when the structure holds
     */
    public function check(?string $html): array
    {
        if (null === $html || '' === trim($html)) {
            return [];
        }

        if (false === preg_match_all('#<h([1-6])\b#i', $html, $matches)) {
            return [];
        }

        $levels = array_map(intval(...), $matches[1]);
        $problems = [];

        $firstLevelCount = \count(array_filter($levels, static fn (int $level): bool => 1 === $level));

        if ($firstLevelCount > 1) {
            $problems[] = $this->translator->trans(
                'The page has %count% level 1 headings. Keep a single one: it is the title of the page.',
                ['%count%' => $firstLevelCount],
                TheliaCMS::DOMAIN_NAME,
            );
        }

        $previous = 0;

        foreach ($levels as $level) {
            if (0 !== $previous && $level > $previous + 1) {
                $problems[] = $this->translator->trans(
                    'The heading structure jumps from level %from% to level %to%. Do not skip a level.',
                    ['%from%' => $previous, '%to%' => $level],
                    TheliaCMS::DOMAIN_NAME,
                );
            }

            $previous = $level;
        }

        return array_values(array_unique($problems));
    }
}
