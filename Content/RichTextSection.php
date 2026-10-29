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

namespace TheliaCMS\Content;

use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Content\Theme\ThemeComponentSections;
use TheliaCMS\TheliaCMS;

/**
 * A piece of free markup, as is.
 *
 * It is the one component that is not a component of the theme: what a seeded
 * legal page holds, what an editor types in a rich-text field, what an import
 * written before the catalogue existed carries. The markup is printed as it
 * stands here; filtering it is the job of the writer, on the rendered page,
 * the same way it filters everything that goes online.
 */
final readonly class RichTextSection implements SectionDefinitionInterface
{
    public const string NAME = 'cms:richtext';

    public function __construct(
        private ?TranslatorInterface $translator = null,
        private ?ThemeComponentSections $theme = null,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Free text');
    }

    public function description(): string
    {
        return $this->trans('Paragraphs, headings and lists written as markup, for text that fits no other section: a legal page, a long article.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            Prop::html('html', $this->trans('Markup'), help: $this->trans('Headings from h2, paragraphs, lists, links and emphasis. No style attribute, no script.')),
        ]);
    }

    public function sample(): array
    {
        return ['html' => '<h2>'.$this->trans('A heading').'</h2><p>'.$this->trans('A paragraph of text, with <a href="/">a link</a> and <strong>emphasis</strong>.').'</p>'];
    }

    public function normalizeProps(array $props): array
    {
        return $this->schema()->normalize($props);
    }

    public function render(array $props, string $locale): string
    {
        $html = (string) ($props['html'] ?? '');

        if ('' === trim($html)) {
            return '';
        }

        // Held at the width of the other sections by the stylesheet of the
        // module; without the wrapper the text sits against the edge of the
        // page while every section around it is centred. The theme styles
        // its lists and headings under a class of its own, which it names.
        $themeClass = $this->theme?->declaration()->richTextClass;
        $class = null === $themeClass ? 'cms-richtext' : 'cms-richtext '.$themeClass;

        return '<div class="'.htmlspecialchars($class, \ENT_QUOTES | \ENT_HTML5).'">'.$html.'</div>';
    }

    private function trans(string $message): string
    {
        return $this->translator?->trans($message, [], TheliaCMS::DOMAIN_NAME) ?? $message;
    }
}
