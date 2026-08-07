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

namespace TheliaCMS\Script;

/**
 * Holds a snippet back until the visitor has agreed to the vendor behind it.
 *
 * The snippet is wrapped in a `<template>`, which the browser parses but does
 * not run: no script executes, no image is fetched, no iframe connects. Marking
 * only the script tags as `text/plain` would leave the pixel next to them free
 * to fire, and a tracking pixel is the tag that needed consent the most.
 */
final readonly class ConsentGate
{
    /** Names both what waits and what it waits for; the reviver reads nothing else. */
    public const string ATTRIBUTE = 'data-cms-consent';

    public static function wrap(string $content, ?string $category): string
    {
        $category = trim((string) $category);

        if ('' === $category) {
            // No category is a claim that the site cannot run without this
            // snippet. The screen makes that claim visible rather than quiet.
            return $content;
        }

        return \sprintf(
            '<template %s="%s">%s</template>',
            self::ATTRIBUTE,
            htmlspecialchars($category, \ENT_QUOTES, 'UTF-8'),
            $content,
        );
    }
}
