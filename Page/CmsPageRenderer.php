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

use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * Renders a CMS page itself instead of handing the view name back to the core
 * renderer.
 *
 * The core resolves a parser from the *active theme root*: with no
 * `cmspage.html.twig` in the theme, `ParserResolver::getParser()` finds no
 * parser at all and the request dies with a 500 instead of rendering. Resolving
 * the parser on a template every theme has, then picking the template
 * ourselves, lets the theme override the page layout while the module still
 * works on a theme that knows nothing about it.
 */
final readonly class CmsPageRenderer
{
    public const string THEME_TEMPLATE = 'cmspage';
    public const string MODULE_TEMPLATE = '@TheliaCMSModule/front/cmspage.html.twig';

    public function __construct(
        private ParserResolver $parserResolver,
        private TemplateHelperInterface $templateHelper,
    ) {
    }

    public function render(PublishedPage $page): string
    {
        $templateDefinition = $this->templateHelper->getActiveFrontTemplate();
        $templatePath = $templateDefinition->getAbsolutePath();

        // 'index' is the one template a theme is guaranteed to ship, so it is a
        // safe probe for "which parser drives this theme".
        $parser = $this->parserResolver->getParser($templatePath, 'index');
        $parser->setTemplateDefinition($templateDefinition, true);

        $template = $parser->supportTemplateRender($templatePath, self::THEME_TEMPLATE)
            ? self::THEME_TEMPLATE.'.'.$parser->getFileExtension()
            : self::MODULE_TEMPLATE;

        return $parser->render($template, ['cms_page' => $page]);
    }
}
