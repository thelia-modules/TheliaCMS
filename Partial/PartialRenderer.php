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

namespace TheliaCMS\Partial;

use Psr\Log\LoggerInterface;

/**
 * Replaces the placeholders a page stores with what the server renders for them.
 *
 * A page holds `<div data-cms-partial="latest-contents" data-props='{"count":3}'></div>`
 * and nothing else — no markup, no template path. The name is resolved against
 * the registry, the props against the definition, and only then is a template
 * rendered: a page can therefore never name a file, and an unknown block
 * disappears instead of breaking the page around it.
 */
final readonly class PartialRenderer
{
    private const string ATTRIBUTE = 'data-cms-partial';

    private const string PROPS_ATTRIBUTE = 'data-props';

    public function __construct(
        private PartialRegistry $registry,
        private PartialProps $props,
        private PartialFragmentRendererInterface $fragments,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param bool $cache whether a partial declaring a lifetime may be served
     *                    from the fragment cache — off in the editor, where the
     *                    author must see what they just changed
     */
    public function substitute(?string $html, string $locale, bool $cache = true): ?string
    {
        // The overwhelming majority of pages hold no partial at all, and this
        // runs on every front-office request.
        if (null === $html || !str_contains($html, self::ATTRIBUTE)) {
            return $html;
        }

        $document = new \DOMDocument();
        $loaded = @$document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="cms-partial-root">'.$html.'</div>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD | \LIBXML_NOERROR | \LIBXML_NOWARNING,
        );

        $root = $loaded ? $document->getElementById('cms-partial-root') : null;

        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $placeholders = (new \DOMXPath($document))->query('//*[@'.self::ATTRIBUTE.']');

        foreach ($placeholders instanceof \DOMNodeList ? iterator_to_array($placeholders) : [] as $placeholder) {
            if ($placeholder instanceof \DOMElement) {
                $this->replace($document, $placeholder, $locale, $cache);
            }
        }

        return $this->innerHtml($document, $root);
    }

    /**
     * Renders one partial on its own — what the editor previews while the block
     * is being set up.
     *
     * @param array<string, mixed> $props
     */
    public function renderOne(string $name, array $props, string $locale, bool $cache = true): string
    {
        $definition = $this->registry->find($name);

        if (!$definition instanceof PartialDefinitionInterface) {
            throw new UnknownPartialException(\sprintf('No block named "%s" is registered.', $name));
        }

        return $this->fragments->render($definition, $this->props->validate($props, $definition), $locale, $cache);
    }

    private function replace(\DOMDocument $document, \DOMElement $placeholder, string $locale, bool $cache): void
    {
        $name = $placeholder->getAttribute(self::ATTRIBUTE);

        try {
            $rendered = $this->renderOne($name, $this->propsOf($placeholder), $locale, $cache);
        } catch (\Throwable $throwable) {
            // A block that cannot render must not take the page down with it:
            // the placeholder goes, the rest of the page is served.
            $this->logger->warning('Thelia CMS: the "{partial}" block was left out of the page: {reason}', [
                'partial' => $name,
                'reason' => $throwable->getMessage(),
            ]);

            $placeholder->parentNode?->removeChild($placeholder);

            return;
        }

        $this->replaceWithMarkup($document, $placeholder, $rendered);
    }

    /**
     * @return array<string, mixed>
     */
    private function propsOf(\DOMElement $placeholder): array
    {
        $raw = $placeholder->getAttribute(self::PROPS_ATTRIBUTE);

        if ('' === trim($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    private function replaceWithMarkup(\DOMDocument $document, \DOMElement $placeholder, string $markup): void
    {
        $parent = $placeholder->parentNode;

        if (!$parent instanceof \DOMNode) {
            return;
        }

        if ('' === trim($markup)) {
            $parent->removeChild($placeholder);

            return;
        }

        $fragment = new \DOMDocument();
        $loaded = @$fragment->loadHTML(
            '<?xml encoding="utf-8" ?><div id="cms-partial-fragment">'.$markup.'</div>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD | \LIBXML_NOERROR | \LIBXML_NOWARNING,
        );

        $rendered = $loaded ? $fragment->getElementById('cms-partial-fragment') : null;

        if (!$rendered instanceof \DOMElement) {
            $parent->removeChild($placeholder);

            return;
        }

        foreach (iterator_to_array($rendered->childNodes) as $child) {
            $parent->insertBefore($document->importNode($child, true), $placeholder);
        }

        $parent->removeChild($placeholder);
    }

    private function innerHtml(\DOMDocument $document, \DOMElement $root): string
    {
        $html = '';

        foreach ($root->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        // DOMDocument closes `<source>`, which has no closing tag in HTML.
        return preg_replace('#</source>#i', '', $html) ?? $html;
    }
}
