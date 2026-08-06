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

namespace TheliaCMS\Seo;

use SEOne\Service\SeoDefaultModels\SeoElementInterface;
use SEOne\Service\SeoDefaultModels\SEOneMicroDataTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Domain\Localization\Service\LangService;
use Thelia\Model\ConfigQuery;
use Thelia\Tools\URL;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\TheliaCMS;

/**
 * Teaches SEOne about the `cmspage` view.
 *
 * Registered from TheliaCMS::configureServices() only when SEOne is installed:
 * the class implements one of its interfaces, so autodiscovery would fatal on a
 * site running without it.
 */
readonly class CmsPageSeoElement implements SeoElementInterface
{
    use SEOneMicroDataTrait;

    public function __construct(
        LangService $langService,
        EventDispatcherInterface $eventDispatcher,
    ) {
        $this->setDependencies(langService: $langService, dispatcher: $eventDispatcher);
    }

    public function supports(string $view): bool
    {
        return $view === $this->getView();
    }

    public function getIdentifier(): string
    {
        return TheliaCMS::PAGE_VIEW.'_id';
    }

    public function getView(): string
    {
        return TheliaCMS::PAGE_VIEW;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function getSeoPageTitle($id): string
    {
        $page = $this->findLocalizedPage($id);

        return $this->firstNonEmpty(
            $page?->getMetaTitle(),
            $page?->getTitle(),
            ConfigQuery::read('store_name'),
        );
    }

    public function getSeoPageDesc($id): string
    {
        $page = $this->findLocalizedPage($id);

        return $this->firstNonEmpty(
            $page?->getMetaDescription(),
            ConfigQuery::read('store_description'),
        );
    }

    public function getSeoPageH1($id, string $type): string
    {
        $page = $this->findLocalizedPage($id);

        return $this->firstNonEmpty($page?->getTitle(), ConfigQuery::read('store_name'));
    }

    public function getSeoMicroData($id, string $type, array $params = []): string
    {
        $page = $this->findLocalizedPage($id);

        $microdata = null === $page ? null : [
            '@context' => 'https://schema.org/',
            '@type' => 'WebPage',
            'url' => $this->pageUrl($page),
            'name' => $page->getTitle(),
            'description' => $page->getMetaDescription(),
        ];

        return $this->getScriptsTag($microdata, $type, $id);
    }

    /**
     * Ancestors first, the page itself last — the CMS tree *is* the breadcrumb.
     */
    public function getSeoBreadcrumb($id): array
    {
        $breadcrumb = [];
        $page = $this->findLocalizedPage($id);
        $guard = 0;

        while ($page instanceof CmsPage && ++$guard < 20) {
            array_unshift($breadcrumb, [
                'url' => $this->pageUrl($page),
                'title' => $page->getTitle(),
            ]);

            $page = $this->findLocalizedPage($page->getParent());
        }

        return $breadcrumb;
    }

    private function findLocalizedPage(int|string|null $id): ?CmsPage
    {
        if (null === $id || '' === $id || 0 === (int) $id) {
            return null;
        }

        $page = CmsPageQuery::create()->findPk((int) $id);

        return $page?->setLocale($this->langService->getLocale());
    }

    private function pageUrl(CmsPage $page): string
    {
        return URL::getInstance()?->retrieve(TheliaCMS::PAGE_VIEW, $page->getId(), $this->langService->getLocale())->toString() ?? '';
    }

    private function firstNonEmpty(?string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if (null !== $candidate && '' !== trim($candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}
