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

use TheliaCMS\Media\MediaResolver;

/**
 * Rewrites the images of a page when it is published.
 *
 * The editor knows one URL per image; a published page needs rather more than
 * that — a modern format for browsers that take it, a set of widths so phones
 * do not download a desktop image, intrinsic dimensions so the layout does not
 * jump while loading, and lazy loading everywhere except the image the visitor
 * sees first.
 *
 * Doing it at publish time keeps it off the request path entirely.
 */
final readonly class ImageRewriter
{
    /**
     * Widths offered in the srcset. Only those below the intrinsic width are
     * kept: upscaling an image serves a bigger file for a blurrier result.
     */
    private const array WIDTHS = [480, 960, 1440];

    private const string SIZES = '(max-width: 1440px) 100vw, 1440px';

    public function __construct(
        private MediaResolver $library,
    ) {
    }

    public function rewrite(?string $html): ?string
    {
        if (null === $html || '' === trim($html)) {
            return $html;
        }

        $document = new \DOMDocument();
        $loaded = @$document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="cms-rewriter-root">'.$html.'</div>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD | \LIBXML_NOERROR | \LIBXML_NOWARNING,
        );

        if (!$loaded) {
            return $html;
        }

        $root = $document->getElementById('cms-rewriter-root');

        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $images = iterator_to_array($document->getElementsByTagName('img'));

        foreach ($images as $position => $image) {
            $this->rewriteImage($document, $image, 0 === $position);
        }

        return $this->innerHtml($document, $root);
    }

    private function rewriteImage(\DOMDocument $document, \DOMElement $image, bool $isFirst): void
    {
        // The first image of a page is the one the visitor is most likely to be
        // waiting for, so it is fetched eagerly and with priority; every other
        // one waits until it is needed.
        if ($isFirst) {
            $image->setAttribute('fetchpriority', 'high');
            $image->removeAttribute('loading');
        } else {
            $image->setAttribute('loading', 'lazy');
        }

        $image->setAttribute('decoding', 'async');

        $media = $this->library->fromUrl($image->getAttribute('src'));

        if (null === $media) {
            // An image the library does not own: nothing to resize or convert,
            // but the loading hints above still apply.
            return;
        }

        if (null !== $media->width && null !== $media->height) {
            $image->setAttribute('width', (string) $media->width);
            $image->setAttribute('height', (string) $media->height);
        }

        $widths = $this->widthsFor($media->width);

        if ([] !== $widths) {
            $image->setAttribute('srcset', $this->srcset($media->id, $widths, $media->format));
            $image->setAttribute('sizes', self::SIZES);
        }

        $this->wrapInPicture($document, $image, $media->id, $widths);
    }

    /**
     * Wraps the image in a `<picture>` offering a WebP alternative.
     *
     * AVIF is not offered: TheliaLibrary only encodes jpg, png, gif, jp2 and
     * webp. Adding it there is worth an upstream change, not a workaround here.
     *
     * @param list<int> $widths
     */
    private function wrapInPicture(\DOMDocument $document, \DOMElement $image, int $imageId, array $widths): void
    {
        $parent = $image->parentNode;

        if (!$parent instanceof \DOMNode || 'picture' === $parent->nodeName) {
            return;
        }

        $picture = $document->createElement('picture');
        $source = $document->createElement('source');
        $source->setAttribute('type', 'image/webp');
        $source->setAttribute('srcset', [] !== $widths
            ? $this->srcset($imageId, $widths, 'webp')
            : $this->url($imageId, null, 'webp'));

        if ([] !== $widths) {
            $source->setAttribute('sizes', self::SIZES);
        }

        $parent->replaceChild($picture, $image);
        $picture->appendChild($source);
        $picture->appendChild($image);
    }

    /**
     * @param list<int> $widths
     */
    private function srcset(int $imageId, array $widths, string $format): string
    {
        return implode(', ', array_map(
            fn (int $width): string => $this->url($imageId, $width, $format).' '.$width.'w',
            $widths,
        ));
    }

    /**
     * The trailing `!` asks the image route to keep the aspect ratio. Without
     * it, `480,` is read as 480 by 480: the image comes back squashed, or
     * rejected outright when that height exceeds the original.
     */
    private function url(int $imageId, ?int $width, string $format): string
    {
        $size = null === $width ? 'max' : $width.',!';

        return '/image-library/'.$imageId.'/full/'.$size.'/0/default.'.$format;
    }

    /**
     * @return list<int>
     */
    private function widthsFor(?int $intrinsicWidth): array
    {
        if (null === $intrinsicWidth) {
            return [];
        }

        return array_values(array_filter(self::WIDTHS, static fn (int $width): bool => $width < $intrinsicWidth));
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
