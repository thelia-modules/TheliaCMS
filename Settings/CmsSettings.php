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

namespace TheliaCMS\Settings;

use TheliaCMS\TheliaCMS;

/**
 * The settings of the module, read as values rather than as strings.
 *
 * They live in `module_config`, so they are scoped to the module and go away
 * when it is uninstalled. Everything that reads them goes through here: a
 * listener asking `'1' === getConfigValue(...)` in its own way is how a flag
 * ends up meaning two different things in two places.
 */
final readonly class CmsSettings
{
    public const string SITE_MODE = 'site_mode';
    public const string NOT_FOUND_PAGE = '404_page_id';
    public const string MAINTENANCE_ACTIVE = 'maintenance_active';
    public const string MAINTENANCE_ALLOWLIST = 'maintenance_allowlist';
    public const string MAINTENANCE_PAGE = 'maintenance_page_id';

    /**
     * How long a client is asked to wait before coming back. Long enough for a
     * deployment, short enough that a crawler comes back the same day — and it
     * is what keeps a maintenance page from being indexed in place of the site.
     */
    public const int RETRY_AFTER = 3600;

    public function siteMode(): SiteMode
    {
        return SiteMode::fromStorage(TheliaCMS::getConfigValue(self::SITE_MODE));
    }

    public function isShowcase(): bool
    {
        return $this->siteMode()->isShowcase();
    }

    public function notFoundPageId(): ?int
    {
        return $this->pageId(self::NOT_FOUND_PAGE);
    }

    public function maintenancePageId(): ?int
    {
        return $this->pageId(self::MAINTENANCE_PAGE);
    }

    public function isMaintenanceActive(): bool
    {
        return '1' === (string) TheliaCMS::getConfigValue(self::MAINTENANCE_ACTIVE, '0');
    }

    /**
     * Addresses that reach the site while it is under maintenance: single IPs,
     * v4 or v6, and CIDR ranges.
     *
     * @return list<string>
     */
    public function maintenanceAllowlist(): array
    {
        $raw = (string) TheliaCMS::getConfigValue(self::MAINTENANCE_ALLOWLIST, '');

        // Typed by a human in a textarea: one per line, or separated by commas,
        // or both.
        $entries = preg_split('/[\s,;]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $entries), static fn (string $entry): bool => '' !== $entry));
    }

    public function save(
        SiteMode $mode,
        ?int $notFoundPageId,
        bool $maintenanceActive,
        string $maintenanceAllowlist,
        ?int $maintenancePageId,
    ): void {
        TheliaCMS::setConfigValue(self::SITE_MODE, $mode->value);
        TheliaCMS::setConfigValue(self::NOT_FOUND_PAGE, (string) ($notFoundPageId ?? ''));
        TheliaCMS::setConfigValue(self::MAINTENANCE_ACTIVE, $maintenanceActive ? '1' : '0');
        TheliaCMS::setConfigValue(self::MAINTENANCE_ALLOWLIST, trim($maintenanceAllowlist));
        TheliaCMS::setConfigValue(self::MAINTENANCE_PAGE, (string) ($maintenancePageId ?? ''));
    }

    private function pageId(string $key): ?int
    {
        $id = (int) TheliaCMS::getConfigValue($key, '0');

        return $id > 0 ? $id : null;
    }
}
