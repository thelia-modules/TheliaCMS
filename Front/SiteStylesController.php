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

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use TheliaCMS\Settings\SiteFonts;
use TheliaCMS\Settings\SiteStyles;
use TheliaCMS\Settings\SiteStylesCss;

/**
 * Serves the stylesheets of the CMS and the fonts they load.
 *
 * One address for the stylesheet, linked by the theme hook on the front and by
 * the builder canvas in the back office: both sides read the same file, which
 * is what keeps the preview honest.
 */
final readonly class SiteStylesController
{
    public function __construct(
        private SiteStyles $styles,
        private SiteStylesCss $css,
        private SiteFonts $fonts,
        private BlockStyles $blocks,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Route('/cms/blocks.css', name: 'cms.block_styles', methods: ['GET'])]
    public function blocks(): Response
    {
        $path = $this->blocks->path();

        if (!is_file($path)) {
            throw new NotFoundHttpException('The stylesheet of the blocks is missing from the module.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'text/css; charset=utf-8');

        // Same bargain as the stylesheet below: the address carries the hash of
        // the file, so a release of the module publishes a new address and what
        // is cached under the old one was never anything else.
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $response;
    }

    #[Route('/cms/site-styles.css', name: 'cms.site_styles', methods: ['GET'])]
    public function stylesheet(): Response
    {
        $typography = $this->styles->typography();

        $fontUrls = [];

        foreach ($typography->fontFiles() as $file) {
            if ($this->fonts->has($file)) {
                $fontUrls[$file] = $this->urls->generate('cms.site_font', ['file' => $file]);
            }
        }

        $response = new Response($this->css->build($typography, $fontUrls), Response::HTTP_OK, [
            'Content-Type' => 'text/css; charset=utf-8',
        ]);

        // The address carries the version of the choices; the file it names
        // never changes. A saved change makes a new address.
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $response;
    }

    #[Route('/cms/fonts/{file}', name: 'cms.site_font', requirements: ['file' => '[a-zA-Z0-9-]+\.woff2'], methods: ['GET'])]
    public function font(string $file): Response
    {
        $path = $this->fonts->path($file);

        if (null === $path) {
            throw new NotFoundHttpException('This site has no such font.');
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'font/woff2');

        // A font never changes under its name: replacing one stores a new
        // name. Cached for a year, like the stylesheet that names it.
        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();
        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, 'true');

        return $response;
    }
}
