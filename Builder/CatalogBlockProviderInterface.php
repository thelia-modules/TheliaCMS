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

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes blocks to the editor panel.
 *
 * This is how a project adds a block of its own: implement this interface
 * anywhere in a module or a bundle, and the blocks it returns appear in the
 * editor next to the ten shipped here. Nothing else to register — the tag is
 * applied automatically.
 *
 * The locale passed in is the language of the page being written, so the sample
 * text of a block reads like the page it lands in. The label of a block and the
 * name of its category are not part of that: they belong to the editor panel
 * and follow the language of the back office, which is what the translator uses
 * when no locale is given.
 *
 * See docs/creating-a-block.md for a commented example.
 */
#[AutoconfigureTag(BlockCatalog::TAG)]
interface CatalogBlockProviderInterface
{
    /**
     * @return list<CatalogBlock>
     */
    public function blocks(string $locale): array;
}
