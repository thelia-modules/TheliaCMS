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
use TheliaCMS\Model\CmsBlockContentQuery;
use TheliaCMS\Model\CmsBlockQuery;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\TheliaCMS;

/**
 * A reusable block: content written once and dropped into any number of pages.
 *
 * The pages hold a reference, never a copy, which is the whole point — the
 * call-to-action banner that appears on twenty pages is edited in one place.
 */
final readonly class BlockPartial implements PartialDefinitionInterface
{
    public const string NAME = 'cms-block';

    /**
     * Long: a reusable block changes rarely, and every write to one drops these
     * fragments explicitly (see PartialCache).
     */
    private const int CACHE_TTL = 3600;

    public function __construct(
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Reusable block');
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/block';
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/block.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::reference('block', $this->trans('Block'), source: $this->urls->generate('admin.cms.partials.sources.blocks')),
        ];
    }

    public function context(array $props, string $locale): array
    {
        $block = CmsBlockQuery::create()
            ->filterById((int) $props['block'])
            ->filterByDeletedAt(null, Criteria::ISNULL)
            ->findOne();

        if (null === $block) {
            return ['html' => null, 'css' => null];
        }

        $content = CmsBlockContentQuery::create()
            ->filterByBlockId($block->getId())
            ->filterByLocale($locale)
            ->findOne();

        // Only the published version, and only in the requested language: a
        // draft must not reach a visitor through a page that happens to embed
        // the block, and a block written in French must not appear on an
        // English page.
        if (null === $content?->getPublishedAt()) {
            return ['html' => null, 'css' => null];
        }

        return [
            'html' => $content->getPublishedHtml(),
            'css' => $content->getPublishedCss(),
        ];
    }

    public function cacheTtl(): ?int
    {
        return self::CACHE_TTL;
    }

    private function trans(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
