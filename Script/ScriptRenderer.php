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

use TheliaCMS\Model\CmsScriptQuery;

/**
 * Writes the active snippets of one placement into the page, each held back or
 * not according to the vendor it names.
 */
final readonly class ScriptRenderer
{
    public function render(ScriptPlacement $placement): string
    {
        $scripts = CmsScriptQuery::create()
            ->filterByActive(1)
            ->filterByPlacement($placement->value)
            ->orderById()
            ->find();

        $out = [];

        foreach ($scripts as $script) {
            $content = trim((string) $script->getContent());

            if ('' === $content) {
                continue;
            }

            $out[] = ConsentGate::wrap($content, $script->getConsentCategory());
        }

        return implode("\n", $out);
    }
}
