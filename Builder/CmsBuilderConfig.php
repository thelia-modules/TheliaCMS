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

use OpenStudio\PageBuilderBundle\Contract\PageBuilderConfigProviderInterface;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Front\BlockStyles;
use TheliaCMS\Partial\PartialRegistry;
use TheliaCMS\Settings\SiteStyles;
use TheliaCMS\TheliaCMS;

/**
 * What the editor needs to know about the site it edits: the stylesheet its
 * canvas renders with, the colours it may offer, and the screen widths it
 * previews.
 */
final readonly class CmsBuilderConfig implements PageBuilderConfigProviderInterface
{
    public const string CANVAS_STYLESHEET_TAG = 'thelia_cms.canvas_stylesheet';

    /**
     * Compiled stylesheet of the front-office theme, as the asset mapper knows
     * it. Themes that build their CSS some other way set `builder_stylesheet`.
     */
    private const string THEME_STYLESHEET = 'styles/app.css';

    /**
     * Neutral, contrast-checked defaults: every colour reaches 4.5:1 against
     * white or against black, so a text block cannot come out unreadable
     * before a project swaps in its own palette.
     */
    private const array DEFAULT_PALETTE = [
        '#111827', '#374151', '#6b7280', '#e5e7eb', '#ffffff',
        '#1d4ed8', '#047857', '#b45309', '#b91c1c', '#6d28d9',
    ];

    public function __construct(
        private AssetMapperInterface $assetMapper,
        private UrlGeneratorInterface $urls,
        private PartialRegistry $partials,
        private TranslatorInterface $translator,
        private SiteStyles $siteStyles,
        private BlockStyles $blockStyles,
        /** @var iterable<CanvasStylesheetProviderInterface> */
        #[AutowireIterator(self::CANVAS_STYLESHEET_TAG)]
        private iterable $canvasStylesheets = [],
    ) {
    }

    public function getConfig(?string $context = null): array
    {
        return [
            'appStylesheet' => $this->themeStylesheet(),
            'icons' => [],
            'palette' => $this->palette(),
            // Where the editor asks the server what a dynamic block looks like.
            'renderTemplateEndpoint' => $this->urls->generate('admin.cms.partials.render'),
        ];
    }

    /**
     * The dynamic blocks the editor offers, with their settings.
     *
     * @return list<array<string, mixed>>
     */
    public function partials(): array
    {
        return $this->partials->toEditor();
    }

    /**
     * Changes whenever the bundled editor does.
     *
     * `module_asset()` returns a stable URL with nothing in it to tell one
     * build from the next, so a browser holding the previous megabyte of
     * JavaScript keeps running it after the module is updated — and the bug
     * that update fixed stays in front of the administrator until they clear
     * their cache.
     */
    public function editorVersion(): string
    {
        $built = __DIR__.'/../templates/backOffice/default-twig/assets/page-builder/editor.js';

        return substr(hash('xxh3', (string) @filemtime($built)), 0, 12);
    }

    /**
     * Canvas widths the editor previews, taken from the theme breakpoints.
     *
     * GrapesJS writes the styles of a device into a `max-width` media query,
     * where a Tailwind theme is written `min-width`. Picking each width one
     * pixel below a theme breakpoint keeps the two from overlapping: the
     * tablet canvas stops exactly where `lg:` starts, the mobile one exactly
     * where `md:` starts.
     *
     * @return array<string, mixed>
     */
    public function editorOptions(): array
    {
        return [
            // GrapesJS otherwise prepends a reset of its own (`*` and `body`)
            // to the page stylesheet, which would restyle the whole site the
            // page is published into. Resets belong to the theme.
            'protectedCss' => '',
            // The wrapper carries the class every published page is wrapped
            // in, so the global styles of the site, scoped to that class,
            // dress the canvas exactly as they dress the front.
            'wrapperClasses' => PageContentNormalizer::CONTAINER_CLASS,
            'canvas' => [
                // What the front loads, in the same order: the socle of the
                // block catalogue, then the styles of the site. Without the
                // socle the canvas showed a stack of unstyled paragraphs while
                // the published page came out laid out, so an editor could not
                // see what they were building.
                'styles' => array_values(array_filter([
                    $this->urls->generate('cms.block_styles', ['v' => $this->blockStyles->version()]),
                    // Blocks contributed by other modules, styled by their own
                    // stylesheet: between the socle and the theme, as on the front.
                    ...$this->contributedStylesheets(),
                    $this->themeStylesheet(),
                    $this->siteStyles->typography()->isEmpty()
                        ? null
                        : $this->urls->generate('cms.site_styles', ['v' => $this->siteStyles->version()]),
                ])),
            ],
            'deviceManager' => [
                'devices' => [
                    ['id' => 'desktop', 'name' => 'Desktop', 'width' => ''],
                    ['id' => 'tablet', 'name' => 'Tablet', 'width' => '768px', 'widthMedia' => '1023px'],
                    ['id' => 'mobile', 'name' => 'Mobile', 'width' => '375px', 'widthMedia' => '767px'],
                ],
            ],
        ];
    }

    /**
     * The wording GrapesJS itself ships in English only.
     *
     * Two places read half in one language and half in another without this:
     * the `target` setting of a link, whose label has no key in the locale
     * files shipped with the library, and the buttons of the rich-text toolbar,
     * whose titles are written as plain attributes outside its i18n. Translated
     * here rather than in a table of strings inside the editor bundle, so a
     * site running in a fourth language gets them too.
     *
     * @return array<string, mixed>
     */
    public function editorLabels(): array
    {
        return [
            'linkTarget' => $this->translate('Opens in'),
            'richText' => [
                'bold' => $this->translate('Bold'),
                'italic' => $this->translate('Italic'),
                'underline' => $this->translate('Underline'),
                'strikethrough' => $this->translate('Strikethrough'),
                'link' => $this->translate('Link'),
                'wrap' => $this->translate('Wrap in a span to style it'),
            ],
            // The words under the three icons of the settings panel, keyed by
            // the identifier GrapesJS gives each tab button.
            'viewTabs' => [
                'open-sm' => $this->translate('Style'),
                'open-tm' => $this->translate('Settings'),
                'open-layers' => $this->translate('Layers'),
            ],
            // "Component settings", renamed for people who were told
            // everything on the page is a block.
            'settingsTitle' => $this->translate('Block settings'),
            // The delete control put on every row of the layer tree.
            'deleteLayer' => $this->translate('Delete the block'),
            // The button of the colour picker that leaves the palette for the
            // full wheel, and the one that comes back.
            'colorPicker' => [
                'more' => $this->translate('More colours'),
                'less' => $this->translate('Back to the palette'),
            ],
            // The writing area in the settings panel of the editorial blocks.
            // Not part of `richText` above: that list names the actions of the
            // canvas toolbar, and is checked against what GrapesJS ships.
            'richTextTrait' => [
                'label' => $this->translate('Content'),
                'bold' => $this->translate('Bold'),
                'italic' => $this->translate('Italic'),
                'underline' => $this->translate('Underline'),
                'strikethrough' => $this->translate('Strikethrough'),
                'bulletList' => $this->translate('Bulleted list'),
                'numberedList' => $this->translate('Numbered list'),
            ],
            // The values of the style options are raw CSS keywords in every
            // language: the locale files of the library have no key for them.
            // Only the sets a page editor meets are covered — the flex,
            // transition and transform vocabulary has no translation that
            // says more than the keyword does.
            'styleOptions' => $this->styleOptionLabels(),
            // Two labels the page builder bundle spells out in French whatever
            // the language of the editor, so an English back office reads half
            // in one language: the block that inserts a list, and the trait
            // that picks an icon.
            'blockLabels' => [
                'list' => $this->translate('List'),
            ],
            'traitLabels' => [
                'icon' => $this->translate('Icon'),
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function styleOptionLabels(): array
    {
        return [
            'text-align' => [
                'left' => $this->translate('Left'),
                'center' => $this->translate('Centered'),
                'right' => $this->translate('Right'),
                'justify' => $this->translate('Justified'),
            ],
            'float' => [
                'none' => $this->translate('None'),
                'left' => $this->translate('Left'),
                'right' => $this->translate('Right'),
            ],
            'display' => [
                'block' => $this->translate('Block'),
                'inline' => $this->translate('Inline'),
                'inline-block' => $this->translate('Inline block'),
                'flex' => $this->translate('Flexible'),
                'none' => $this->translate('Hidden'),
            ],
            'position' => [
                'static' => $this->translate('Normal'),
                'relative' => $this->translate('Relative'),
                'absolute' => $this->translate('Absolute'),
                'fixed' => $this->translate('Pinned'),
            ],
            'border-style-sub' => [
                'none' => $this->translate('None'),
                'solid' => $this->translate('Solid'),
                'dotted' => $this->translate('Dotted'),
                'dashed' => $this->translate('Dashed'),
                'double' => $this->translate('Double'),
                'groove' => $this->translate('Groove'),
                'ridge' => $this->translate('Ridge'),
                'inset' => $this->translate('Sunken'),
                'outset' => $this->translate('Raised'),
            ],
            'background-repeat-sub' => [
                'repeat' => $this->translate('Tiled'),
                'repeat-x' => $this->translate('Tiled horizontally'),
                'repeat-y' => $this->translate('Tiled vertically'),
                'no-repeat' => $this->translate('Not repeated'),
            ],
            'background-position-sub' => [
                'left top' => $this->translate('Top left'),
                'left center' => $this->translate('Center left'),
                'left bottom' => $this->translate('Bottom left'),
                'right top' => $this->translate('Top right'),
                'right center' => $this->translate('Center right'),
                'right bottom' => $this->translate('Bottom right'),
                'center top' => $this->translate('Top center'),
                'center center' => $this->translate('Center'),
                'center bottom' => $this->translate('Bottom center'),
            ],
            'background-attachment-sub' => [
                'scroll' => $this->translate('Scrolls with the page'),
                'fixed' => $this->translate('Stays in place'),
                'local' => $this->translate('Scrolls with the content'),
            ],
            'background-size-sub' => [
                'auto' => $this->translate('Original size'),
                'cover' => $this->translate('Fills the area'),
                'contain' => $this->translate('Fits inside'),
            ],
            'box-shadow-type' => [
                'inset' => $this->translate('Inner'),
            ],
        ];
    }

    private function translate(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }

    /**
     * @return list<string>
     */
    private function contributedStylesheets(): array
    {
        $stylesheets = [];

        foreach ($this->canvasStylesheets as $provider) {
            foreach ($provider->canvasStylesheets() as $stylesheet) {
                $stylesheets[] = $stylesheet;
            }
        }

        return array_values(array_unique($stylesheets));
    }

    private function themeStylesheet(): ?string
    {
        $configured = (string) TheliaCMS::getConfigValue('builder_stylesheet', '');

        if ('' !== $configured) {
            return $configured;
        }

        return $this->assetMapper->getAsset(self::THEME_STYLESHEET)?->publicPath;
    }

    /**
     * @return list<string>
     */
    private function palette(): array
    {
        $configured = $this->siteStyles->palette();

        return [] === $configured ? self::DEFAULT_PALETTE : $configured;
    }
}
