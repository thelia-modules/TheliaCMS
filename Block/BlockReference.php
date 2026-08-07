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

namespace TheliaCMS\Block;

/**
 * Whether a stored page refers to a given reusable block.
 *
 * There is no join to make: a block reaches a page through the settings of a
 * `cms-block` placeholder, and those settings are JSON — written with entities
 * inside an HTML attribute, and with escaped slashes inside the editor project.
 * Both forms have to be recognised, or a block still in use looks unused and an
 * editor is invited to delete it.
 */
final readonly class BlockReference
{
    public function isReferencedIn(string $storedContent, int $blockId): bool
    {
        $plain = str_replace(['&quot;', '\\"', '\\/'], ['"', '"', '/'], $storedContent);

        // The id may be stored as a number or as a string, and the editor is
        // free to put spaces around the colon.
        return 1 === preg_match('/"block"\s*:\s*"?'.$blockId.'"?\s*[,}]/', $plain);
    }
}
