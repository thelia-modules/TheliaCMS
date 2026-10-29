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

namespace TheliaCMS\Content;

use Thelia\Domain\Ai\AiProvenance;

/**
 * The mark of a generated content (AI Act, article 50(2)) as the module
 * stores it: the JSON of `AiProvenance::toArray()`, NULL for a content a
 * person wrote. The core writes and reads the shape; the module only keeps
 * it next to the content and copies it with the content.
 *
 * It is a column and not an attribute of the HTML: the sanitizer of the
 * published HTML removes every `data-*` it does not know, and the template
 * prints the mark when the page is served.
 */
final class ProvenanceMark
{
    public static function encode(?AiProvenance $provenance): ?string
    {
        return null === $provenance ? null : json_encode($provenance->toArray(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * What a file or a column holds, read back and written again in the shape
     * the core gives it: NULL for anything that is not a valid mark, so an
     * import cannot plant an attribute of its own on a public page.
     */
    public static function normalize(mixed $stored): ?string
    {
        return \is_string($stored) || \is_array($stored) ? self::encode(AiProvenance::fromStored($stored)) : null;
    }
}
