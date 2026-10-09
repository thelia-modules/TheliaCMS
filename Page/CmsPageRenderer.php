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

namespace TheliaCMS\Page;

use TheliaCMS\Front\ThemeTemplateRenderer;
use TheliaCMS\Partial\PartialRenderer;

/**
 * Renders a CMS page with the template of its type: see PageTemplateResolver
 * for the order the theme and the module are tried in.
 *
 * The dynamic blocks of the page are resolved here rather than at publish time —
 * a news list stored in the page would be the news of the day it was published.
 */
final readonly class CmsPageRenderer
{
    /** @deprecated since 1.2.0, removed in 2.0.0: the template depends on the type of the page, see PageTemplateResolver */
    public const string THEME_TEMPLATE = PageTemplateResolver::BASE_TEMPLATE;

    /** @deprecated since 1.2.0, removed in 2.0.0: the template depends on the type of the page, see PageTemplateResolver */
    public const string MODULE_TEMPLATE = '@TheliaCMSModule/front/cmspage.html.twig';

    public function __construct(
        private ThemeTemplateRenderer $templates,
        private PageTemplateResolver $templateResolver,
        private PartialRenderer $partials,
    ) {
    }

    public function render(PublishedPage $page): string
    {
        $html = $this->partials->substitute($page->html, $page->locale);

        return $this->templates->renderTemplate($this->templateResolver->resolve($page->pageType)->name, [
            'cms_page' => $page->withHtml((string) $html),
        ]);
    }
}
