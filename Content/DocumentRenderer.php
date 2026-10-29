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

/**
 * The HTML of a document: its sections rendered in order, inside the container
 * the stylesheets of the module and of the site address.
 *
 * Run on a document the validator has accepted. A section whose component has
 * gone since — a module deactivated — is an error, not a hole: the page was
 * stored with it and must not silently lose a part when it is published again.
 */
final readonly class DocumentRenderer
{
    /** Class of the container, the one `blocks.css` and the site styles target. */
    public const string CONTAINER_CLASS = 'cms-page-content';

    /** Attribute of the first element of a section, carrying the id of the section. */
    public const string SECTION_ATTRIBUTE = 'data-cms-section';

    /** Class of the element wrapping a section that has an appearance. */
    public const string STYLED_CLASS = 'cms-section-styled';

    public function __construct(
        private SectionCatalog $catalog,
    ) {
    }

    /**
     * @throws UnknownSectionException when a section names a component the catalogue no longer has
     */
    public function render(Document $document, string $locale): string
    {
        $fragments = [];

        foreach ($document->sections as $section) {
            $definition = $this->catalog->find($section->component);

            if (null === $definition) {
                throw UnknownSectionException::for($section);
            }

            $fragment = $definition->render($section->props, $locale);

            $fragments[] = $section->appearance->isEmpty()
                ? self::anchored($fragment, $section->id)
                : self::styled($fragment, $section);
        }

        return \sprintf('<div class="%s">%s</div>', self::CONTAINER_CLASS, implode("\n", $fragments));
    }

    /**
     * A section with an appearance is wrapped in an element of the whole
     * width that carries it: the first element of a component is often its
     * container, narrower than the page, and a background there would be a
     * box rather than a band. The wrapper is the section for the content
     * screen too.
     */
    private static function styled(string $fragment, Section $section): string
    {
        return \sprintf(
            '<div class="%s" %s="%s" style="%s">%s</div>',
            self::STYLED_CLASS,
            self::SECTION_ATTRIBUTE,
            htmlspecialchars($section->id, \ENT_QUOTES | \ENT_HTML5),
            htmlspecialchars($section->appearance->style(), \ENT_QUOTES | \ENT_HTML5),
            $fragment,
        );
    }

    /**
     * The first element of the fragment is told which section it renders,
     * so the content screen can point at a section in the preview. A
     * fragment that opens with no element — empty, text alone — is left as
     * it is.
     */
    private static function anchored(string $fragment, string $sectionId): string
    {
        $anchored = preg_replace(
            '/^(\s*(?:<!--.*?-->\s*)*<[a-z][a-z0-9-]*)(?=[\s\/>])/is',
            '$1 '.self::SECTION_ATTRIBUTE.'="'.htmlspecialchars($sectionId, \ENT_QUOTES | \ENT_HTML5).'"',
            $fragment,
            1,
        );

        return $anchored ?? $fragment;
    }
}
