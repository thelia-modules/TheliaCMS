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

namespace TheliaCMS\Front;

use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;

/**
 * Renders a front-office template the theme may override.
 *
 * The core resolves a parser from the *active theme root*, so asking it for a
 * template the theme does not ship finds no parser at all and the request dies
 * with a 500. Resolving the parser on a template every theme has, then picking
 * the file ourselves, is what lets a theme take over the layout of a page or of
 * a block while the module still works on a theme that knows nothing about it.
 *
 * Not `readonly`: the parser is resolved once per request rather than once per
 * block, because configuring it appends the theme and module directories to the
 * template loader every time.
 */
final class ThemeTemplateRenderer
{
    private ?ParserInterface $parser = null;

    private string $themePath = '';

    public function __construct(
        private readonly ParserResolver $parserResolver,
        private readonly TemplateHelperInterface $templateHelper,
    ) {
    }

    /**
     * @param string               $themeTemplate    path relative to the theme root, without extension
     * @param string               $fallbackTemplate namespaced Twig path used when the theme ships none
     * @param array<string, mixed> $context
     */
    public function render(string $themeTemplate, string $fallbackTemplate, array $context = []): string
    {
        $parser = $this->parser();

        $template = $parser->supportTemplateRender($this->themePath, $themeTemplate)
            ? $themeTemplate.'.'.$parser->getFileExtension()
            : $fallbackTemplate;

        return $parser->render($template, $context);
    }

    /**
     * Whether the active theme ships the view of that name.
     *
     * A view no theme renders answers 404 for every address pointing at it, so
     * this is what tells a caller there is nothing on the other side. Returns
     * false rather than throwing when no parser can be resolved at all: callers
     * ask this while deciding what to do about a request that already failed,
     * and a broken theme turning a 404 into a 500 helps nobody.
     */
    public function themeRenders(string $view): bool
    {
        try {
            return $this->parser()->supportTemplateRender($this->themePath, $view);
        } catch (\Throwable) {
            return false;
        }
    }

    private function parser(): ParserInterface
    {
        if (null !== $this->parser) {
            return $this->parser;
        }

        $templateDefinition = $this->templateHelper->getActiveFrontTemplate();
        $this->themePath = $templateDefinition->getAbsolutePath();

        // 'index' is the one template a theme is guaranteed to ship, so it is a
        // safe probe for "which parser drives this theme".
        $parser = $this->parserResolver->getParser($this->themePath, 'index');
        $parser->setTemplateDefinition($templateDefinition, true);

        return $this->parser = $parser;
    }
}
