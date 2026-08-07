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
 * Renders a CMS page: the theme layout if there is one, the layout shipped with
 * the module otherwise.
 *
 * The dynamic blocks of the page are resolved here rather than at publish time —
 * a news list stored in the page would be the news of the day it was published.
 */
final readonly class CmsPageRenderer
{
    public const string THEME_TEMPLATE = 'cmspage';
    public const string MODULE_TEMPLATE = '@TheliaCMSModule/front/cmspage.html.twig';

    public function __construct(
        private ThemeTemplateRenderer $templates,
        private PartialRenderer $partials,
    ) {
    }

    public function render(PublishedPage $page): string
    {
        $html = $this->partials->substitute($page->html, $page->locale);

        return $this->templates->render(self::THEME_TEMPLATE, self::MODULE_TEMPLATE, [
            'cms_page' => $page->withHtml((string) $html),
        ]);
    }
}
