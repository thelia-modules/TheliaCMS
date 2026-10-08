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
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * Picks the template a page of a given type is rendered with:
 *
 *   1. `cmspage-{type}` as the front parser finds it — the theme, the themes it
 *      inherits from, and the front templates other modules contribute;
 *   2. `cmspage-{type}` shipped in `templates/front/` of this module;
 *   3. `cmspage` of the theme;
 *   4. `cmspage` of this module.
 *
 * The front renderer and the page types screen both ask this class, so what
 * the back office announces is what a visitor gets.
 *
 * Only asks the parser whether a file exists, never configures it: configuring
 * the shared Twig parser on the front theme adds the theme directories to the
 * loader of the back-office screens, which is where the types screen runs.
 */
final readonly class PageTemplateResolver
{
    public const string BASE_TEMPLATE = 'cmspage';

    private const string MODULE_NAMESPACE = '@TheliaCMSModule/front/';

    public function __construct(
        private ParserResolver $parserResolver,
        private TemplateHelperInterface $templateHelper,
        private string $moduleFrontDirectory = __DIR__.'/../templates/front',
    ) {
    }

    public function resolve(string $pageType): PageTemplate
    {
        $typed = self::BASE_TEMPLATE.'-'.PageTypeCode::orDefault($pageType);
        $themePath = $this->templateHelper->getActiveFrontTemplate()->getAbsolutePath();
        $parser = $this->themeParser($themePath);

        if (null !== $parser && $parser->supportTemplateRender($themePath, $typed)) {
            return new PageTemplate($typed.'.'.$parser->getFileExtension(), PageTemplateSource::Theme);
        }

        if (is_file($this->moduleFrontDirectory.'/'.$typed.'.html.twig')) {
            return new PageTemplate(self::MODULE_NAMESPACE.$typed.'.html.twig', PageTemplateSource::Module);
        }

        if (null !== $parser && $parser->supportTemplateRender($themePath, self::BASE_TEMPLATE)) {
            return new PageTemplate(self::BASE_TEMPLATE.'.'.$parser->getFileExtension(), PageTemplateSource::ThemeFallback);
        }

        return new PageTemplate(self::MODULE_NAMESPACE.self::BASE_TEMPLATE.'.html.twig', PageTemplateSource::ModuleFallback);
    }

    /**
     * The parser that drives the theme, found the way the core finds it — the
     * first one able to render 'index', the one template every theme ships —
     * without going through ParserResolver::getParser(), which also makes the
     * parser found the current one of the request.
     */
    private function themeParser(string $themePath): ?ParserInterface
    {
        foreach ($this->parserResolver->getParsers() as $parser) {
            if ($parser->supportTemplateRender($themePath, 'index')) {
                return $parser;
            }
        }

        return null;
    }
}
