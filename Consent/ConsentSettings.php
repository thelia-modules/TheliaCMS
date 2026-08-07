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

namespace TheliaCMS\Consent;

use TheliaCMS\TheliaCMS;

/**
 * What the site needs in order to ask visitors before loading anything of
 * theirs: the Axeptio project, and what each of its vendors is allowed to turn
 * on once the visitor has agreed.
 */
final readonly class ConsentSettings
{
    public const string CLIENT_ID = 'axeptio_client_id';
    public const string COOKIES_VERSION = 'axeptio_cookies_version';
    public const string CONSENT_MAP = 'axeptio_consent_map';

    /**
     * The Google Consent Mode signals, all denied until told otherwise.
     *
     * Since 15 June 2026 `ad_storage` is what decides whether a Google Ads
     * conversion is counted at all, so a site that never emits these defaults
     * and never updates them measures nothing rather than measuring without
     * consent.
     *
     * @var list<string>
     */
    public const array CONSENT_MODE_SIGNALS = [
        'ad_storage',
        'ad_user_data',
        'ad_personalization',
        'analytics_storage',
    ];

    public function clientId(): ?string
    {
        $value = trim((string) TheliaCMS::getConfigValue(self::CLIENT_ID, ''));

        return '' === $value ? null : $value;
    }

    public function cookiesVersion(): ?string
    {
        $value = trim((string) TheliaCMS::getConfigValue(self::COOKIES_VERSION, ''));

        return '' === $value ? null : $value;
    }

    public function isConfigured(): bool
    {
        return null !== $this->clientId();
    }

    /**
     * Axeptio vendor => the Consent Mode signals it grants.
     *
     * A vendor missing from the map still gates the snippets that name it; it
     * simply does not move any Google signal. That is the common case for a
     * chat widget or a heat map.
     *
     * @return array<string, list<string>>
     */
    public function consentMap(): array
    {
        return self::parseConsentMap((string) TheliaCMS::getConfigValue(self::CONSENT_MAP, ''));
    }

    /**
     * Reads the map the integrator typed, keeping only signals Google knows.
     *
     * An unreadable or empty map falls back to the default rather than to
     * nothing: a site whose JSON has a stray comma should still grant consent
     * for the two products almost every site uses.
     *
     * @return array<string, list<string>>
     */
    public static function parseConsentMap(string $json): array
    {
        $decoded = json_decode($json, true);

        if (!\is_array($decoded)) {
            return self::defaultConsentMap();
        }

        $map = [];

        foreach ($decoded as $vendor => $signals) {
            if (!\is_string($vendor) || '' === trim($vendor) || !\is_array($signals)) {
                continue;
            }

            $allowed = array_values(array_unique(array_intersect(
                array_map(static fn (mixed $signal): string => \is_scalar($signal) ? (string) $signal : '', $signals),
                self::CONSENT_MODE_SIGNALS,
            )));

            if ([] !== $allowed) {
                $map[$vendor] = $allowed;
            }
        }

        return [] === $map ? self::defaultConsentMap() : $map;
    }

    public function save(?string $clientId, ?string $cookiesVersion, ?string $consentMap): void
    {
        TheliaCMS::setConfigValue(self::CLIENT_ID, trim((string) $clientId));
        TheliaCMS::setConfigValue(self::COOKIES_VERSION, trim((string) $cookiesVersion));
        TheliaCMS::setConfigValue(self::CONSENT_MAP, trim((string) $consentMap));
    }

    /**
     * The vendor names Axeptio ships with for the two Google products, so a
     * site that only uses those works without writing any JSON.
     *
     * @return array<string, list<string>>
     */
    public static function defaultConsentMap(): array
    {
        return [
            'google_analytics' => ['analytics_storage'],
            'google_ads' => ['ad_storage', 'ad_user_data', 'ad_personalization'],
        ];
    }
}
