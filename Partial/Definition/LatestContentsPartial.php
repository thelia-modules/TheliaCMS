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

use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\TheliaCMS;

/**
 * The latest published contents, optionally taken from one folder.
 *
 * News on a page written six months ago has to be today's news, which is the
 * whole reason partials exist: the page stores the request, the server answers
 * it when a visitor arrives.
 */
final readonly class LatestContentsPartial implements PartialDefinitionInterface
{
    /** Long enough to take the list off most requests, short enough that news feels live. */
    private const int CACHE_TTL = 300;

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function name(): string
    {
        return 'latest-contents';
    }

    public function label(): string
    {
        return $this->trans('Latest news');
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/latest-contents';
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/latest-contents.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::integer('count', $this->trans('How many'), default: 3, min: 1, max: 12),
            PartialProp::reference('folder', $this->trans('Folder'), source: $this->urls->generate('admin.cms.partials.sources.folders'), required: false, help: $this->trans('Leave empty to take the latest of every folder.')),
            PartialProp::text('heading', $this->trans('Heading'), required: false, help: $this->trans('Shown above the list. Leave empty for no heading.')),
        ];
    }

    public function context(array $props, string $locale): array
    {
        $query = ContentQuery::create()
            ->filterByVisible(1)
            ->orderByCreatedAt(Criteria::DESC)
            ->limit((int) ($props['count'] ?? 3));

        if (null !== $props['folder']) {
            $query->useContentFolderQuery()
                ->filterByFolderId((int) $props['folder'])
                ->endUse();
        }

        return [
            'heading' => $props['heading'],
            'contents' => array_map(
                fn (Content $content): array => $this->present($content, $locale),
                iterator_to_array($query->find(), false),
            ),
        ];
    }

    public function cacheTtl(): ?int
    {
        return self::CACHE_TTL;
    }

    /**
     * Data, never markup: what a theme puts around a news item is the theme's
     * business.
     *
     * @return array<string, mixed>
     */
    private function present(Content $content, string $locale): array
    {
        $content->setLocale($locale);

        return [
            'id' => (int) $content->getId(),
            'title' => (string) $content->getTitle(),
            'summary' => trim((string) $content->getChapo()),
            'url' => $content->getUrl($locale),
            'published_at' => $content->getCreatedAt(),
        ];
    }

    private function trans(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
