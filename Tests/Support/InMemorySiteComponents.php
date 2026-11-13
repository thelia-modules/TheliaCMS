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

use TheliaCMS\Content\Site\KeptSiteComponents;
use TheliaCMS\Content\Site\SiteComponent;

/**
 * Components of the site held in memory: the kept ones, and prototypes the
 * site holds but `cms_component` may not draw.
 */
final readonly class InMemorySiteComponents implements KeptSiteComponents
{
    /**
     * @param list<SiteComponent> $kept
     * @param list<SiteComponent> $prototypes
     */
    public function __construct(
        private array $kept = [],
        private array $prototypes = [],
    ) {
    }

    public function kept(string $code): ?SiteComponent
    {
        foreach ($this->kept as $component) {
            if ($component->code === $code) {
                return $component;
            }
        }

        return null;
    }

    public function all(): array
    {
        $all = [...$this->kept, ...$this->prototypes];
        usort($all, static fn (SiteComponent $a, SiteComponent $b): int => strcmp($a->code, $b->code));

        return $all;
    }
}
