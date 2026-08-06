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

namespace TheliaCMS\Menu;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use TheliaCMS\TheliaCMS;

/**
 * Resolved menus, cached per code, locale and host.
 *
 * A menu is read on every single page of the site and resolves one row per
 * entry, so it is cached; and because it is cached, every change that can alter
 * it has to say so — saving a menu, renaming a page, publishing or unpublishing
 * one. All of them land on `invalidate()`, which drops the whole set rather than
 * trying to guess which menus a page appears in.
 */
final readonly class MenuCache
{
    private const string TAG = 'thelia_cms_menu';

    /** Safety net only: the cache is invalidated explicitly on every write. */
    private const int DEFAULT_TTL = 3600;

    public function __construct(
        private TagAwareCacheInterface $cache,
    ) {
    }

    /**
     * @param callable(): array<mixed> $compute
     *
     * @return array<mixed>
     */
    public function get(string $code, string $locale, string $host, callable $compute): array
    {
        // Cache keys reject reserved characters, and a host is user input as far
        // as this code is concerned.
        $key = \sprintf('thelia_cms_menu.%s.%s.%s', preg_replace('/[^a-z0-9_-]/i', '_', $code), $locale, substr(sha1($host), 0, 12));

        return $this->cache->get($key, function (ItemInterface $item) use ($compute): array {
            $item->tag(self::TAG);
            $item->expiresAfter($this->ttl());

            return $compute();
        });
    }

    public function invalidate(): void
    {
        $this->cache->invalidateTags([self::TAG]);
    }

    private function ttl(): int
    {
        $configured = (int) TheliaCMS::getConfigValue('cache_ttl', (string) self::DEFAULT_TTL);

        return $configured > 0 ? $configured : self::DEFAULT_TTL;
    }
}
