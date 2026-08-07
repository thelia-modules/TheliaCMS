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

namespace TheliaCMS\Partial;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use TheliaCMS\Front\ThemeTemplateRenderer;

/**
 * Renders the template of a partial, through a cache of its own when the
 * partial says its content may age.
 *
 * A fragment cache rather than no cache at all: a block that changes every hour
 * must not be the reason a whole page is recomputed on every request, and it
 * must not be the reason the page cache is turned off either (SPEC §3.5).
 */
final readonly class PartialFragmentRenderer implements PartialFragmentRendererInterface
{
    /** Dropping this tag clears every cached fragment of every partial. */
    public const string TAG = 'thelia_cms_partial';

    public function __construct(
        private ThemeTemplateRenderer $templates,
        private TagAwareCacheInterface $cache,
        private RequestStack $requestStack,
    ) {
    }

    public function render(PartialDefinitionInterface $definition, array $props, string $locale, bool $cache = true): string
    {
        $ttl = $definition->cacheTtl();

        if (!$cache || null === $ttl || $ttl <= 0) {
            return $this->renderNow($definition, $props, $locale);
        }

        return $this->cache->get(
            $this->keyFor($definition, $props, $locale),
            function (ItemInterface $item) use ($definition, $props, $locale, $ttl): string {
                $item->expiresAfter($ttl);
                $item->tag([self::TAG, self::tagFor($definition->name())]);

                return $this->renderNow($definition, $props, $locale);
            },
        );
    }

    /**
     * Tag of one partial, so a write can drop the fragments of the block it
     * changed without flushing the others.
     */
    public static function tagFor(string $name): string
    {
        return self::TAG.'.'.preg_replace('/[^a-z0-9_]/i', '_', $name);
    }

    /**
     * @param array<string, string|int|bool|null> $props
     */
    private function renderNow(PartialDefinitionInterface $definition, array $props, string $locale): string
    {
        return $this->templates->render(
            $definition->themeTemplate(),
            $definition->fallbackTemplate(),
            $definition->context($props, $locale),
        );
    }

    /**
     * @param array<string, string|int|bool|null> $props
     */
    private function keyFor(PartialDefinitionInterface $definition, array $props, string $locale): string
    {
        ksort($props);

        // The host is part of the key because the URLs a partial renders are
        // absolute: on a site serving one domain per language, a fragment
        // cached under one domain would send visitors to the other.
        $host = $this->requestStack->getMainRequest()?->getHost() ?? 'cli';

        return \sprintf(
            'thelia_cms_partial.%s.%s.%s',
            preg_replace('/[^a-z0-9_-]/i', '_', $definition->name()),
            $locale,
            substr(sha1($host.'|'.json_encode($props, \JSON_THROW_ON_ERROR)), 0, 16),
        );
    }
}
