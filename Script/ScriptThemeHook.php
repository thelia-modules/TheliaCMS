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

use Thelia\Core\Hook\Theme\ThemeHookInterface;

/**
 * Writes the third-party snippets into the theme, at the place each one asks
 * for.
 *
 * This is what replaces pasting a tag into the theme by hand: the same snippets
 * are on every page, removing one is a click, and the ones that need consent
 * are held back on their own rather than by whoever remembers to.
 */
final readonly class ScriptThemeHook implements ThemeHookInterface
{
    public function __construct(
        private ScriptRenderer $renderer,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return null !== $this->placementFor($hookName);
    }

    public function render(string $hookName, array $parameters): string
    {
        $placement = $this->placementFor($hookName);

        return null === $placement ? '' : $this->renderer->render($placement);
    }

    private function placementFor(string $hookName): ?ScriptPlacement
    {
        foreach (ScriptPlacement::cases() as $placement) {
            if ($placement->hook() === $hookName) {
                return $placement;
            }
        }

        return null;
    }
}
