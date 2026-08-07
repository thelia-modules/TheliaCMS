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
 * A post from a social network, fetched when a visitor asks for it.
 *
 * Social embeds are the worst offenders of the three: the official snippets
 * load a script that fingerprints the visitor and ties the visit to their
 * account, on every page view, whether or not anyone scrolls far enough to see
 * the post. This renders a card with a link, and swaps in the embed on click.
 *
 * Only the four networks below, each with its documented embed address: an
 * editor picks a network and pastes the address of a post, never a URL that
 * ends up in an iframe as it was typed.
 */
final readonly class SocialFacadePartial implements PartialDefinitionInterface
{
    /**
     * @var array<string, array{label: string, embed: string, host: string}>
     */
    public const array NETWORKS = [
        'instagram' => [
            'label' => 'Instagram',
            'embed' => 'https://www.instagram.com/p/%s/embed',
            'host' => 'instagram.com',
        ],
        'x' => [
            'label' => 'X',
            'embed' => 'https://platform.twitter.com/embed/Tweet.html?id=%s',
            'host' => 'x.com',
        ],
        'facebook' => [
            'label' => 'Facebook',
            'embed' => 'https://www.facebook.com/plugins/post.php?href=%s',
            'host' => 'facebook.com',
        ],
        'linkedin' => [
            'label' => 'LinkedIn',
            'embed' => 'https://www.linkedin.com/embed/feed/update/%s',
            'host' => 'linkedin.com',
        ],
    ];

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function name(): string
    {
        return 'social-facade';
    }

    public function label(): string
    {
        return $this->trans('Social post');
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/social-facade';
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/social-facade.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::choice('network', $this->trans('Network'), array_map(
                static fn (array $network): string => $network['label'],
                self::NETWORKS,
            ), 'instagram'),
            PartialProp::text('post', $this->trans('Post'), required: true, max: 255, help: $this->trans('The identifier of the post, or its full address.')),
            PartialProp::text('summary', $this->trans('What the post says'), required: false, max: 255, help: $this->trans('Shown before the post is loaded, and by anything that cannot load it at all.')),
        ];
    }

    public function context(array $props, string $locale): array
    {
        $network = self::NETWORKS[(string) $props['network']] ?? self::NETWORKS['instagram'];
        $post = $this->reference((string) $props['post']);

        return [
            'network' => $network['label'],
            'summary' => $props['summary'],
            'embed_url' => \sprintf($network['embed'], rawurlencode($post)),
            'page_url' => $this->pageUrl((string) $props['network'], $post),
            'notice' => $this->translator->trans(
                'Loading the post fetches it from %network%, which may set cookies on your device.',
                ['%network%' => $network['label']],
                TheliaCMS::DOMAIN_NAME,
                $locale,
            ),
            'show_label' => $this->translator->trans('Show the post', [], TheliaCMS::DOMAIN_NAME, $locale),
        ];
    }

    public function cacheTtl(): ?int
    {
        return 3600;
    }

    /**
     * Facebook and LinkedIn embed a whole address, the other two an identifier.
     * Everything else is stripped: what comes out of here goes into a URL.
     */
    private function reference(string $value): string
    {
        $value = trim($value);

        if (str_starts_with($value, 'https://') && preg_match('#^https://[A-Za-z0-9.-]+/[A-Za-z0-9._~:/?#\[\]@!$&\'()*+,;=%-]*$#', $value)) {
            return $value;
        }

        return preg_replace('/[^A-Za-z0-9_:.-]/', '', $value) ?? '';
    }

    private function pageUrl(string $network, string $post): ?string
    {
        if (str_starts_with($post, 'https://')) {
            return $post;
        }

        return match ($network) {
            'instagram' => 'https://www.instagram.com/p/'.rawurlencode($post).'/',
            'x' => 'https://x.com/i/status/'.rawurlencode($post),
            default => null,
        };
    }

    private function trans(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
