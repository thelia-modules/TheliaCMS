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

use Thelia\Core\Hook\Theme\ThemeHookInterface;
use Twig\Environment;

/**
 * Puts the consent layer at the very top of the head, before anything else the
 * page loads.
 *
 * Order is the whole point. The Consent Mode defaults are emitted first, so any
 * Google tag that starts afterwards already knows it may not store anything.
 * The Axeptio SDK comes next. The snippets of the "Scripts and measurement"
 * screen come later, in `layout.head.bottom` and in the body, and the ones that
 * name a category stay inert until this file wakes them.
 */
final readonly class ConsentThemeHook implements ThemeHookInterface
{
    private const string TEMPLATE = '@TheliaCMSModule/front/consent.html.twig';

    public function __construct(
        private Environment $twig,
        private ConsentSettings $settings,
    ) {
    }

    public function supports(string $hookName): bool
    {
        return 'layout.head.top' === $hookName;
    }

    public function render(string $hookName, array $parameters): string
    {
        if (!$this->settings->isConfigured()) {
            // No consent platform means nothing may be waiting on one either:
            // the reviver would have nothing to listen to, and a site with no
            // third-party snippet has nothing to gate.
            return '';
        }

        return $this->twig->render(self::TEMPLATE, [
            'client_id' => $this->settings->clientId(),
            'cookies_version' => $this->settings->cookiesVersion(),
            'consent_map' => $this->settings->consentMap(),
            'signals' => ConsentSettings::CONSENT_MODE_SIGNALS,
        ]);
    }
}
