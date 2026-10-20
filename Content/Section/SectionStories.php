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

namespace TheliaCMS\Content\Section;

use FlexyBundle\Toolkit\ComponentStatus;
use FlexyBundle\Toolkit\Story;
use FlexyBundle\Toolkit\StoryProviderInterface;
use TheliaCMS\Content\SectionCatalog;

/**
 * Lists the sections of the module in the toolkit of the Flexy theme, next
 * to the components of the theme, each rendered with its sample props.
 *
 * Registered only when Flexy is there (see TheliaCMS::configureServices): on
 * another theme there is no toolkit to list anything in.
 */
final readonly class SectionStories implements StoryProviderInterface
{
    public const string CATEGORY = 'CMS';

    public function __construct(
        private SectionCatalog $catalog,
    ) {
    }

    /** The slug the toolkit gives the page that lists every section: the category and the name. */
    public const string ALL_SECTIONS_SLUG = 'cms-all-sections';

    public function stories(): array
    {
        // First: one page with every section, for whoever writes a brief and
        // wants to know what the generator can use.
        $all = \dirname(__DIR__, 2).'/templates/front/sections/toolkit/all.html.twig';
        $stories = [new Story(
            category: self::CATEGORY,
            name: 'All sections',
            twigPath: '@TheliaCMSModule/front/sections/toolkit/all.html.twig',
            sourcePath: $all,
            status: ComponentStatus::READY,
        )];

        foreach ($this->catalog->all() as $definition) {
            if (!$definition instanceof AbstractTemplateSection) {
                continue;
            }

            $slug = $definition->storySlug();
            $source = \dirname(__DIR__, 2).'/templates/front/sections/toolkit/'.$slug.'.html.twig';

            if (!is_readable($source)) {
                continue;
            }

            $stories[] = new Story(
                category: self::CATEGORY,
                name: $definition->label(),
                twigPath: '@TheliaCMSModule/front/sections/toolkit/'.$slug.'.html.twig',
                sourcePath: $source,
                status: ComponentStatus::READY,
            );
        }

        return $stories;
    }
}
