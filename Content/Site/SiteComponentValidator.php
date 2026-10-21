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

namespace TheliaCMS\Content\Site;

use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\Pipeline\PublishedContentSanitizer;

/**
 * Says whether a component written by the model can be kept, and gives it
 * back as it will be: the sample normalised by its own props, the stylesheet
 * filtered of what reaches another host.
 *
 * Every problem is listed at once, as the validator of documents does, so
 * the model that wrote the component can fix them all in one turn.
 */
final readonly class SiteComponentValidator
{
    public function __construct(
        private SitePropReader $props = new SitePropReader(),
        private SiteTemplateRenderer $renderer = new SiteTemplateRenderer(),
        private SiteCssScoper $scoper = new SiteCssScoper(),
        private PublishedContentSanitizer $sanitizer = new PublishedContentSanitizer(),
    ) {
    }

    /**
     * @throws InvalidSiteComponentException listing every problem found
     */
    public function validate(SiteComponent $component): SiteComponent
    {
        $problems = [];

        if (1 !== preg_match('/^[A-Z][A-Za-z0-9]{1,39}$/', $component->code)) {
            $problems[] = \sprintf('The code "%s" is not a name in PascalCase of 2 to 40 letters and digits, such as "PriceTable".', $component->code);
        }

        if ('' === trim($component->label) || mb_strlen($component->label) > 120) {
            $problems[] = 'The label is 1 to 120 characters.';
        }

        if ('' === trim($component->description) || mb_strlen($component->description) > 500) {
            $problems[] = 'The description is 1 to 500 characters: what the component is for and when to use it.';
        }

        $schema = null;

        try {
            $schema = $this->props->read($component->props);
        } catch (InvalidSiteComponentException $exception) {
            $problems = [...$problems, ...$exception->problems];
        }

        $templateProblems = $this->renderer->problems($component->template);
        $problems = [...$problems, ...$templateProblems];

        // The publication filters what a page shows, but a draft is drawn in
        // the back office before it: what runs code is refused here already.
        if (1 === preg_match('/<\s*(script|style|iframe|object|embed|form|base|meta|link)\b|\son[a-z]+\s*=|javascript:/i', $component->template, $match)) {
            $problems[] = \sprintf('The template holds "%s": a component draws markup and nothing that runs, loads or submits; its styles go in its stylesheet.', $match[0]);
        }

        if ([] === $templateProblems && null !== $wording = self::wording($component->template)) {
            $problems[] = \sprintf('The template writes text of its own ("%s"): every text a component shows is a prop, so that it can be written in every language of the site.', $wording);
        }

        // Checked as written, so the model is told what it wrote wrong; kept
        // as filtered, so nothing in it reaches another host.
        $css = (string) $this->sanitizer->css($component->css);

        try {
            $this->scoper->scope($component->css, $component->scopeClass());
        } catch (InvalidSiteComponentException $exception) {
            $problems = [...$problems, ...$exception->problems];
        }

        $sample = $component->sample;

        if (null !== $schema) {
            try {
                $sample = $schema->normalize($component->sample);
            } catch (InvalidDocumentException $exception) {
                foreach ($exception->problems as $problem) {
                    $problems[] = 'The sample: '.$problem;
                }
            }
        }

        if ([] !== $problems) {
            throw InvalidSiteComponentException::because($problems);
        }

        $kept = new SiteComponent($component->code, trim($component->label), trim($component->description), $component->props, $sample, $component->template, $css);
        \assert(null !== $schema);

        if ('' === (new SiteComponentSection($kept, $schema, $this->renderer))->render($sample, 'en_US')) {
            throw InvalidSiteComponentException::because(['The sample draws nothing: it is what the toolkit and the generator are shown, so it fills the component.']);
        }

        return $kept;
    }

    /**
     * The first text the template writes itself, outside of what it prints,
     * of its tags and of its markup; null when it writes none.
     */
    private static function wording(string $template): ?string
    {
        $text = (string) preg_replace(['/\{#.*?#\}/s', '/\{%.*?%\}/s', '/\{\{.*?\}\}/s', '/<(script|style)\b.*?<\/\1>/is', '/<[^>]*>/s', '/&[a-z0-9#]+;/i'], ' ', $template);

        if (1 !== preg_match('/[^\s]*\p{L}[^\s]*(?:\s+[^\s]*\p{L}[^\s]*){0,4}/u', $text, $match)) {
            return null;
        }

        return mb_substr(trim($match[0]), 0, 60);
    }
}
