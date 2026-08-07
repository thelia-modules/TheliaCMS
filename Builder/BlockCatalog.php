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

namespace TheliaCMS\Builder;

use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\TheliaCMS;

/**
 * The ten blocks an editor starts from.
 *
 * Written on the server rather than in JavaScript for two reasons: the sample
 * text has to be in the language of the *page* being written, not of the back
 * office; and a project adding a block of its own does it here, in PHP, next to
 * the ten it can read (see docs/creating-a-block.md).
 *
 * Every markup below answers to the same rules, and they are the reason a block
 * is accepted in review:
 *
 *  - a block is a `<section>` named by its own heading (`aria-labelledby`), so
 *    it appears in the landmark list of a screen reader under a useful name;
 *  - headings go down one level at a time, and the hero is the only block
 *    carrying an `h1`;
 *  - anything clickable is a link or a button, never a `div` with a handler,
 *    and is at least 24 by 24 pixels once styled (WCAG 2.2);
 *  - nothing depends on dragging: the accordion is `<details>`/`<summary>`,
 *    which works with a keyboard on its own, and testimonials are a list rather
 *    than a carousel;
 *  - no colour is written into the markup: the theme stylesheet owns the
 *    palette, which is where the contrast ratios were checked.
 */
final readonly class BlockCatalog
{
    private const string CATEGORY = 'Page blocks';

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<CatalogBlock>
     */
    public function blocks(string $locale): array
    {
        return [
            $this->hero($locale),
            $this->mediaText($locale),
            $this->callToAction($locale),
            $this->quote($locale),
            $this->testimonials($locale),
            $this->figures($locale),
            $this->logos($locale),
            $this->gallery($locale),
            $this->questions($locale),
            $this->section($locale),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public function toEditor(string $locale): array
    {
        return array_map(
            static fn (CatalogBlock $block): array => $block->toEditor(),
            $this->blocks($locale),
        );
    }

    public function category(string $locale): string
    {
        return $this->trans(self::CATEGORY, $locale);
    }

    private function hero(string $locale): CatalogBlock
    {
        return $this->block('cms-hero', 'Hero', $locale, <<<HTML
            <section class="cms-hero" aria-labelledby="cms-hero-title">
                <div class="cms-hero__body">
                    <h1 class="cms-hero__title" id="cms-hero-title">{$this->trans('The sentence that says what you do', $locale)}</h1>
                    <p class="cms-hero__text">{$this->trans('One or two lines to say it in more detail, in the words your customers use.', $locale)}</p>
                    <p class="cms-hero__actions">
                        <a class="cms-button" href="/contact">{$this->trans('Get in touch', $locale)}</a>
                    </p>
                </div>
            </section>
            HTML);
    }

    private function mediaText(string $locale): CatalogBlock
    {
        return $this->block('cms-media-text', 'Text and image', $locale, <<<HTML
            <section class="cms-media-text" aria-labelledby="cms-media-text-title">
                <figure class="cms-media-text__media">
                    <img data-gjs-type="image" alt=""/>
                </figure>
                <div class="cms-media-text__body">
                    <h2 class="cms-media-text__title" id="cms-media-text-title">{$this->trans('A title for this section', $locale)}</h2>
                    <p>{$this->trans('Describe one thing here, in a few sentences. Add a second block for the next one.', $locale)}</p>
                    <p><a class="cms-link" href="/">{$this->trans('Read more', $locale)}</a></p>
                </div>
            </section>
            HTML);
    }

    private function callToAction(string $locale): CatalogBlock
    {
        return $this->block('cms-cta', 'Call to action', $locale, <<<HTML
            <section class="cms-cta" aria-labelledby="cms-cta-title">
                <div class="cms-cta__body">
                    <h2 class="cms-cta__title" id="cms-cta-title">{$this->trans('Ready to start?', $locale)}</h2>
                    <p class="cms-cta__text">{$this->trans('One line that says what happens when they click.', $locale)}</p>
                </div>
                <p class="cms-cta__actions"><a class="cms-button" href="/contact">{$this->trans('Contact us', $locale)}</a></p>
            </section>
            HTML);
    }

    private function quote(string $locale): CatalogBlock
    {
        return $this->block('cms-quote', 'Quote', $locale, <<<HTML
            <figure class="cms-quote">
                <blockquote class="cms-quote__text">
                    <p>{$this->trans('A sentence worth repeating, from someone worth quoting.', $locale)}</p>
                </blockquote>
                <figcaption class="cms-quote__author">{$this->trans('Their name, what they do', $locale)}</figcaption>
            </figure>
            HTML);
    }

    /**
     * A list rather than a carousel: a carousel hides most of its content, and
     * the ones that do not is because they can be dragged — which nobody using
     * a keyboard can do.
     */
    private function testimonials(string $locale): CatalogBlock
    {
        $card = static fn (string $quote, string $author): string => <<<HTML
            <li class="cms-testimonials__item">
                <figure>
                    <blockquote><p>{$quote}</p></blockquote>
                    <figcaption>{$author}</figcaption>
                </figure>
            </li>
            HTML;

        $quote = $this->trans('What a customer said about working with you.', $locale);
        $author = $this->trans('Their name, their company', $locale);

        return $this->block('cms-testimonials', 'Testimonials', $locale, <<<HTML
            <section class="cms-testimonials" aria-labelledby="cms-testimonials-title">
                <h2 class="cms-testimonials__title" id="cms-testimonials-title">{$this->trans('What our customers say', $locale)}</h2>
                <ul class="cms-testimonials__list">
                    {$card($quote, $author)}
                    {$card($quote, $author)}
                    {$card($quote, $author)}
                </ul>
            </section>
            HTML);
    }

    private function figures(string $locale): CatalogBlock
    {
        $figure = static fn (string $value, string $label): string => <<<HTML
            <li class="cms-figures__item">
                <p class="cms-figures__value">{$value}</p>
                <p class="cms-figures__label">{$label}</p>
            </li>
            HTML;

        return $this->block('cms-figures', 'Key figures', $locale, <<<HTML
            <section class="cms-figures" aria-labelledby="cms-figures-title">
                <h2 class="cms-figures__title" id="cms-figures-title">{$this->trans('In figures', $locale)}</h2>
                <ul class="cms-figures__list">
                    {$figure('12', $this->trans('years in business', $locale))}
                    {$figure('480', $this->trans('projects delivered', $locale))}
                    {$figure('98 %', $this->trans('customers who come back', $locale))}
                </ul>
            </section>
            HTML);
    }

    private function logos(string $locale): CatalogBlock
    {
        $logo = static fn (): string => '<li class="cms-logos__item"><img data-gjs-type="image" alt=""/></li>';

        return $this->block('cms-logos', 'Logos', $locale, <<<HTML
            <section class="cms-logos" aria-labelledby="cms-logos-title">
                <h2 class="cms-logos__title" id="cms-logos-title">{$this->trans('They trust us', $locale)}</h2>
                <ul class="cms-logos__list">
                    {$logo()}{$logo()}{$logo()}{$logo()}
                </ul>
            </section>
            HTML);
    }

    private function gallery(string $locale): CatalogBlock
    {
        $image = static fn (): string => '<li class="cms-gallery__item"><figure><img data-gjs-type="image" alt=""/></figure></li>';

        return $this->block('cms-gallery', 'Gallery', $locale, <<<HTML
            <section class="cms-gallery" aria-labelledby="cms-gallery-title">
                <h2 class="cms-gallery__title" id="cms-gallery-title">{$this->trans('Gallery', $locale)}</h2>
                <ul class="cms-gallery__list">
                    {$image()}{$image()}{$image()}
                </ul>
            </section>
            HTML);
    }

    /**
     * `<details>`/`<summary>`: opens with a keyboard, is announced as expanded
     * or collapsed, and needs no JavaScript at all — which is what keeps a
     * published page free of builder scripts.
     */
    private function questions(string $locale): CatalogBlock
    {
        $item = fn (): string => <<<HTML
            <details class="cms-questions__item">
                <summary class="cms-questions__question">{$this->trans('A question your customers ask', $locale)}</summary>
                <div class="cms-questions__answer"><p>{$this->trans('The answer, in plain words.', $locale)}</p></div>
            </details>
            HTML;

        return $this->block('cms-questions', 'Questions and answers', $locale, <<<HTML
            <section class="cms-questions" aria-labelledby="cms-questions-title">
                <h2 class="cms-questions__title" id="cms-questions-title">{$this->trans('Questions and answers', $locale)}</h2>
                {$item()}
                {$item()}
                {$item()}
            </section>
            HTML);
    }

    private function section(string $locale): CatalogBlock
    {
        return $this->block('cms-section', 'Section', $locale, <<<HTML
            <section class="cms-section" aria-labelledby="cms-section-title">
                <h2 class="cms-section__title" id="cms-section-title">{$this->trans('Section title', $locale)}</h2>
                <div class="cms-section__body">
                    <p>{$this->trans('Drop other blocks in here to group them on a background of their own.', $locale)}</p>
                </div>
            </section>
            HTML);
    }

    private function block(string $id, string $label, string $locale, string $content): CatalogBlock
    {
        return new CatalogBlock(
            id: $id,
            label: $this->trans($label, $locale),
            // Collapsed: what the editor exports is reformatted anyway, and the
            // indentation of a heredoc would end up in the page as text nodes.
            content: preg_replace('/\s*\n\s*/', '', trim($content)) ?? $content,
            category: $this->category($locale),
        );
    }

    private function trans(string $message, string $locale): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME, $locale);
    }
}
