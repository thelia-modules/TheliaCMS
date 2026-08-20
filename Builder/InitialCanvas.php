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

/**
 * What the editor canvas starts from.
 *
 * The editor restores its own project when it has one, and the markup stored
 * next to it is then only a rendering of that project. Two states have no
 * usable project and the stored markup is all there is: a page seeded by the
 * application or brought in by an import, which has HTML and CSS but no
 * project, and a project saved while the canvas was empty, which reads as
 * `{"pages":[]}`.
 *
 * The second one is the one that costs a page: the editor loads it, drops
 * whatever the canvas held, and the next save writes that emptiness over the
 * HTML and the CSS. Handing the markup back turns it into the harmless state
 * the first one already was.
 */
final readonly class InitialCanvas
{
    public function htmlFor(?string $projectData, ?string $html): ?string
    {
        if (null === $html || '' === $html) {
            return null;
        }

        $project = json_decode((string) $projectData, true);

        if (\is_array($project) && [] !== ($project['pages'] ?? [])) {
            return null;
        }

        return $html;
    }
}
