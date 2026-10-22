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

use Psr\Log\LoggerInterface;

/**
 * The stylesheet of the components the site kept: each one's rules under the
 * class it is drawn inside, in the layer of the stylesheet of the blocks, so
 * a theme that restyles them keeps the upper hand.
 *
 * Read from the rows at every call, cheap at the size of a few components;
 * the address carries the hash of what it serves.
 */
final readonly class SiteComponentStyles
{
    public function __construct(
        private SiteComponentRepository $components,
        private SiteCssScoper $scoper,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function css(): string
    {
        $rules = [];

        foreach ($this->components->ready() as $component) {
            if ('' !== $scoped = $this->scoped($component)) {
                $rules[] = '/* '.$component->name().' */'."\n".$scoped;
            }
        }

        return [] === $rules ? '' : "@layer cms {\n".implode("\n", $rules)."\n}\n";
    }

    /**
     * The rules of one component, scoped; empty when it has none or they no
     * longer scope, which is logged rather than fatal.
     */
    public function scoped(SiteComponent $component): string
    {
        if ('' === trim($component->css)) {
            return '';
        }

        try {
            return $this->scoper->scope($component->css, $component->scopeClass());
        } catch (InvalidSiteComponentException $exception) {
            $this->logger?->warning('TheliaCMS: the stylesheet of the component {name} of the site cannot be scoped: {message}', ['name' => $component->name(), 'message' => $exception->getMessage()]);

            return '';
        }
    }

    public function version(): string
    {
        return self::versionOf($this->css());
    }

    public static function versionOf(string $css): string
    {
        return substr(hash('xxh3', $css), 0, 12);
    }
}
