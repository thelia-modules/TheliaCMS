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

            $fragments[] = $definition->render($section->props, $locale);
        }

        return \sprintf('<div class="%s">%s</div>', self::CONTAINER_CLASS, implode("\n", $fragments));
    }
}
