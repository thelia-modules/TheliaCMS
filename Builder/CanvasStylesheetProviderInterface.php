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
 * Hands the editor canvas the stylesheet of blocks a module contributes.
 *
 * A module that adds blocks through {@see CatalogBlockProviderInterface} and
 * styles them with a stylesheet of its own loads that stylesheet on the front,
 * but the canvas only knew the socle of the block catalogue and the theme: the
 * blocks were laid out once published and came out unstyled while an editor
 * dragged them in. Implement this interface next to the block provider and the
 * canvas loads the stylesheet too, after the socle and before the theme, the
 * order the front uses, so the theme keeps the last word.
 *
 * Nothing else to register — the tag is applied automatically.
 */
#[AutoconfigureTag(CmsBuilderConfig::CANVAS_STYLESHEET_TAG)]
interface CanvasStylesheetProviderInterface
{
    /**
     * Public URLs of the stylesheets, as the front loads them.
     *
     * @return list<string>
     */
    public function canvasStylesheets(): array;
}
