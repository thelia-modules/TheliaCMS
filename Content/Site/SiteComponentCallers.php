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

use TheliaCMS\Content\PropSchema;

/**
 * The components of the site that draw another with `cms_component`
 * (ADR 15), and what a new version of the one they draw would do to them:
 * a component another draws is not deleted, and a version that no longer
 * takes what a caller passes is not kept.
 */
final readonly class SiteComponentCallers
{
    public function __construct(
        private KeptSiteComponents $components,
        private SiteTemplateRenderer $renderer,
        private SitePropReader $props = new SitePropReader(),
    ) {
    }

    /**
     * @return list<SiteComponent> the components, prototypes included, whose template draws that one, by code
     */
    public function of(string $code): array
    {
        return array_values(array_filter(
            $this->components->all(),
            fn (SiteComponent $component): bool => $component->code !== $code && [] !== $this->callsTo($component, $code),
        ));
    }

    /**
     * @return list<string> one sentence per call a version of the component would no longer take, none when it takes them all
     */
    public function breakages(SiteComponent $version, PropSchema $schema): array
    {
        $read = SiteComponentCallFit::byName($schema->props);
        $breakages = [];

        foreach ($this->of($version->code) as $caller) {
            try {
                $own = SiteComponentCallFit::byName($this->props->read($caller->props)->props);
            } catch (InvalidSiteComponentException) {
                $own = [];
            }

            foreach ($this->callsTo($caller, $version->code) as $call) {
                foreach ($call->keys as $key) {
                    $problems = !isset($read[$key])
                        ? [\sprintf('Line %d: it passes "%s", which this version no longer declares.', $call->line, $key)]
                        : (isset($call->props[$key], $own[$call->props[$key]]) ? SiteComponentCallFit::mismatches($own[$call->props[$key]], $read[$key], $call) : []);

                    foreach ($problems as $problem) {
                        $breakages[] = \sprintf('The component %s draws this one, and would draw it wrong. %s', $caller->code, $problem);
                    }
                }
            }
        }

        return $breakages;
    }

    /**
     * @return list<SiteComponentCall>
     */
    private function callsTo(SiteComponent $caller, string $code): array
    {
        return array_values(array_filter($this->renderer->calls($caller->template), static fn (SiteComponentCall $call): bool => $call->code === $code));
    }
}
