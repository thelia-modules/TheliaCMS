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

namespace TheliaCMS\Partial\Definition;

use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\TheliaCMS;

/**
 * A map that is fetched when a visitor asks to see it.
 *
 * Same reason as the video: an embedded map hands the IP address of every
 * visitor to a mapping service before anyone has agreed to anything. Until the
 * button is pressed, what the page holds is the address in plain text, which is
 * also what most visitors were looking for.
 *
 * OpenStreetMap only for now, and deliberately: it is the one that answers
 * without an API key and without advertising cookies. A Google Maps option
 * would need the consent gate of §3.3, not just a facade.
 */
final readonly class MapFacadePartial implements PartialDefinitionInterface
{
    /** Roughly a street-level view; the box is derived from it. */
    private const float DEFAULT_SPAN = 0.008;

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return 'map-facade';
    }

    public function label(): string
    {
        return $this->trans('Map');
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/map-facade';
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/map-facade.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::text('label', $this->trans('Place'), required: true, max: 255, help: $this->trans('The address as you would write it on a letter. Shown before the map is loaded.')),
            PartialProp::text('latitude', $this->trans('Latitude'), required: true, max: 20, help: $this->trans('For example 45.7772.')),
            PartialProp::text('longitude', $this->trans('Longitude'), required: true, max: 20, help: $this->trans('For example 3.0870.')),
            PartialProp::integer('zoom', $this->trans('Zoom'), default: 16, min: 3, max: 19),
        ];
    }

    public function context(array $props, string $locale): array
    {
        $latitude = $this->coordinate((string) $props['latitude'], 90.0);
        $longitude = $this->coordinate((string) $props['longitude'], 180.0);
        $span = self::DEFAULT_SPAN * 2 ** (16 - (int) $props['zoom']);

        return [
            'label' => $props['label'],
            'embed_url' => \sprintf(
                'https://www.openstreetmap.org/export/embed.html?bbox=%s,%s,%s,%s&layer=mapnik&marker=%s,%s',
                $this->number($longitude - $span),
                $this->number($latitude - $span / 2),
                $this->number($longitude + $span),
                $this->number($latitude + $span / 2),
                $this->number($latitude),
                $this->number($longitude),
            ),
            'page_url' => \sprintf(
                'https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=%d/%s/%s',
                $this->number($latitude),
                $this->number($longitude),
                (int) $props['zoom'],
                $this->number($latitude),
                $this->number($longitude),
            ),
            'notice' => $this->translator->trans(
                'Loading the map fetches it from OpenStreetMap, which receives your IP address.',
                [],
                TheliaCMS::DOMAIN_NAME,
                $locale,
            ),
            'show_label' => $this->translator->trans('Show the map', [], TheliaCMS::DOMAIN_NAME, $locale),
            'open_label' => $this->translator->trans('Open in OpenStreetMap', [], TheliaCMS::DOMAIN_NAME, $locale),
        ];
    }

    public function cacheTtl(): ?int
    {
        return 86400;
    }

    /**
     * A coordinate is a number inside a known range. Anything else would end up
     * in a URL, and a URL built from unchecked input is how an embed becomes an
     * open redirect.
     */
    private function coordinate(string $value, float $bound): float
    {
        $number = (float) str_replace(',', '.', trim($value));

        return max(-$bound, min($bound, $number));
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
    }

    private function trans(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
