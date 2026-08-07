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

namespace TheliaCMS\ImportExport\Admin;

use Thelia\Core\Security\SecurityContext;
use TheliaCMS\ImportExport\SiteDocument;
use TheliaCMS\ImportExport\SiteExporter;
use TheliaCMS\ImportExport\SiteImporter;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageTemplate;
use TheliaCMS\Model\CmsPageTemplateQuery;
use TheliaCMS\Page\Admin\CmsActivityLog;
use TheliaCMS\Page\PageSlugSource;

/**
 * Keeps a page aside as a template, and starts a page from one.
 *
 * A template holds the export document of the page it was taken from, so it is
 * read by the same code that reads an import file, and a template built here
 * can be handed to another site as a file.
 */
final readonly class PageTemplateWriter
{
    public function __construct(
        private SiteExporter $exporter,
        private SiteImporter $importer,
        private SecurityContext $securityContext,
        private CmsActivityLog $activityLog,
        private PageSlugSource $slugs = new PageSlugSource(),
    ) {
    }

    /**
     * @throws \DomainException when the code is already taken by another template
     */
    public function saveFromPage(CmsPage $page, string $title, ?string $description): CmsPageTemplate
    {
        $code = $this->freeCode($this->slugs->slugify($title));
        $adminId = $this->securityContext->getAdminUser()?->getId();

        $template = (new CmsPageTemplate())
            ->setCode($code)
            ->setTitle($title)
            ->setDescription(null !== $description && '' !== trim($description) ? trim($description) : null)
            ->setPayload(json_encode($this->exporter->exportPage($page), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR))
            ->setCreatedBy($adminId)
            ->setUpdatedBy($adminId);

        $template->save();

        $this->activityLog->record('CREATE', (int) $page->getId(), \sprintf('CMS page #%d saved as template "%s"', $page->getId(), $code));

        return $template;
    }

    /**
     * @throws \InvalidArgumentException when the stored document cannot be read
     */
    public function createPage(CmsPageTemplate $template, string $title, int $parentId, string $locale): CmsPage
    {
        $page = $this->importer->importPageFrom(
            SiteDocument::fromJson((string) $template->getPayload()),
            $parentId,
            $title,
            $locale,
        );

        $this->activityLog->record('CREATE', (int) $page->getId(), \sprintf('CMS page #%d created from template "%s"', $page->getId(), (string) $template->getCode()));

        return $page;
    }

    public function delete(CmsPageTemplate $template): void
    {
        $code = (string) $template->getCode();
        $template->delete();

        $this->activityLog->record('DELETE', 0, \sprintf('CMS template "%s" deleted', $code));
    }

    /**
     * Two templates may well be called "Landing page"; their codes may not.
     */
    private function freeCode(string $wanted): string
    {
        $code = '' !== $wanted ? substr($wanted, 0, 50) : 'template';
        $candidate = $code;
        $suffix = 1;

        while (null !== CmsPageTemplateQuery::create()->findOneByCode($candidate)) {
            ++$suffix;
            $candidate = substr($code, 0, 47).'-'.$suffix;
        }

        return $candidate;
    }
}
