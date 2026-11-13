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

namespace TheliaCMS\Tests\Support;

use TheliaCMS\Content\SectionDefinitionInterface;
use TheliaCMS\Content\Site\DrawableSections;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentSection;
use TheliaCMS\Content\Site\SitePropReader;
use TheliaCMS\Content\Site\SiteTemplateRenderer;

/**
 * What the catalogue would offer `cms_component()`: components of the site,
 * drawn by the renderer set once both exist, and other sections as given.
 */
final class InMemoryDrawableSections implements DrawableSections
{
    public ?SiteTemplateRenderer $renderer = null;

    /**
     * @param list<SiteComponent>              $components
     * @param list<SectionDefinitionInterface> $others
     */
    public function __construct(
        private readonly array $components = [],
        private readonly array $others = [],
    ) {
    }

    public function find(string $name): ?SectionDefinitionInterface
    {
        foreach ($this->components as $component) {
            if ($component->name() === $name) {
                return new SiteComponentSection($component, (new SitePropReader())->read($component->props), $this->renderer ?? new SiteTemplateRenderer());
            }
        }

        foreach ($this->others as $section) {
            if ($section->name() === $name) {
                return $section;
            }
        }

        return null;
    }
}
