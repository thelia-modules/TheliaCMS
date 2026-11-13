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

namespace TheliaCMS\Content\Site;

use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Content\SectionDefinitionInterface;
use TheliaCMS\Content\Theme\ThemeComponentSection;

/**
 * What the catalogue offers, read when a call is drawn: the catalogue builds
 * the sections of the site, which hold the renderer that reads this, so it is
 * reached through a closure rather than injected.
 */
final readonly class CatalogueDrawableSections implements DrawableSections
{
    /**
     * @param \Closure(): SectionCatalog $catalog
     */
    public function __construct(
        #[AutowireServiceClosure(SectionCatalog::class)]
        private \Closure $catalog,
    ) {
    }

    /**
     * A component of the site or of the theme the catalogue offers; never a
     * section of the module (a text, a picture, a form), which a component
     * draws with its own markup.
     */
    public function find(string $name): ?SectionDefinitionInterface
    {
        $section = ($this->catalog)()->offered()[$name] ?? null;

        return $section instanceof SiteComponentSection || $section instanceof ThemeComponentSection ? $section : null;
    }
}
