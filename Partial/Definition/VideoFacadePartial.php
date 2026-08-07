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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\TheliaCMS;

/**
 * A video that loads nothing until a visitor asks for it.
 *
 * Dropping a YouTube iframe in a page calls Google on every visit, from every
 * visitor, before anyone has consented to anything. What this renders instead
 * is a poster hosted by the site and a button; the player is fetched when the
 * button is pressed, and not one second earlier (the pattern the CNIL
 * documents for third-party embeds).
 *
 * The provider is a fixed list rather than a URL an editor types: a free URL is
 * a way to embed anything, and the sanitiser refuses iframes for exactly that
 * reason.
 */
final readonly class VideoFacadePartial implements PartialDefinitionInterface
{
    /**
     * Where a video is fetched from, once. `youtube-nocookie.com` is the host
     * Google documents for embeds that must not write cookies before playback.
     *
     * @var array<string, array{label: string, embed: string, page: string}>
     */
    public const array PROVIDERS = [
        'youtube' => [
            'label' => 'YouTube',
            'embed' => 'https://www.youtube-nocookie.com/embed/%s?autoplay=1',
            'page' => 'https://www.youtube.com/watch?v=%s',
        ],
        'vimeo' => [
            'label' => 'Vimeo',
            'embed' => 'https://player.vimeo.com/video/%s?autoplay=1',
            'page' => 'https://vimeo.com/%s',
        ],
        'dailymotion' => [
            'label' => 'Dailymotion',
            'embed' => 'https://www.dailymotion.com/embed/video/%s?autoplay=1',
            'page' => 'https://www.dailymotion.com/video/%s',
        ],
    ];

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function name(): string
    {
        return 'video-facade';
    }

    public function label(): string
    {
        return $this->trans('Video');
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/video-facade';
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/video-facade.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::choice('provider', $this->trans('Platform'), array_map(
                static fn (array $provider): string => $provider['label'],
                self::PROVIDERS,
            ), 'youtube'),
            PartialProp::text('video', $this->trans('Video identifier'), required: true, max: 64, help: $this->trans('The identifier at the end of the video address, for example dQw4w9WgXcQ.')),
            PartialProp::text('title', $this->trans('Video title'), required: true, max: 255, help: $this->trans('Read out before the button, so people know what they are about to play.')),
            PartialProp::reference('poster', $this->trans('Poster image'), source: $this->urls->generate('admin.cms.partials.sources.images'), required: false, help: $this->trans('Shown in place of the video. Taken from your media library, so nothing is requested from the platform.')),
        ];
    }

    public function context(array $props, string $locale): array
    {
        $provider = self::PROVIDERS[(string) $props['provider']] ?? self::PROVIDERS['youtube'];
        $video = $this->identifier((string) $props['video']);

        return [
            'provider' => $provider['label'],
            'title' => $props['title'],
            'embed_url' => \sprintf($provider['embed'], rawurlencode($video)),
            'page_url' => \sprintf($provider['page'], rawurlencode($video)),
            'poster_url' => null === $props['poster'] ? null : \sprintf('/image-library/%d/full/960,!/0/default.webp', $props['poster']),
            'notice' => $this->translator->trans(
                'Playing the video loads it from %provider%, which may set cookies on your device.',
                ['%provider%' => $provider['label']],
                TheliaCMS::DOMAIN_NAME,
                $locale,
            ),
            'play_label' => $this->translator->trans('Play the video', [], TheliaCMS::DOMAIN_NAME, $locale),
        ];
    }

    /**
     * Nothing about a video changes between two visits, and the fragment holds
     * no personal data: it is cached for a day.
     */
    public function cacheTtl(): ?int
    {
        return 86400;
    }

    /**
     * Editors paste whole addresses. The identifier is what the platform needs,
     * and keeping only the characters platforms actually use is also what stops
     * a pasted `?` from turning into extra query parameters.
     */
    private function identifier(string $value): string
    {
        if (preg_match('#(?:v=|/video/|/embed/|youtu\.be/|vimeo\.com/)([A-Za-z0-9_-]{4,64})#', $value, $matches)) {
            return $matches[1];
        }

        return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?? '';
    }

    private function trans(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
