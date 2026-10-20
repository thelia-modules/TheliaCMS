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

namespace TheliaCMS\Generation\Admin;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TheliaCMS\Content\Section\SectionStories;

/**
 * Where to see every section the generator can use: the page the module
 * hangs in the toolkit of the Flexy theme. The toolkit answers only where
 * the kernel runs in debug, and only when the theme is there; elsewhere
 * there is nothing to link to.
 */
final readonly class ToolkitLink
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
    ) {
    }

    public function url(): ?string
    {
        if (!$this->debug) {
            return null;
        }

        try {
            return $this->urls->generate('toolkit_show', ['slug' => SectionStories::ALL_SECTIONS_SLUG]);
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
