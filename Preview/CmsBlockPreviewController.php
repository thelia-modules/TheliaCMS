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

namespace TheliaCMS\Preview;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use TheliaCMS\Block\Admin\CmsBlockRepository;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Page\CmsPageRenderer;
use TheliaCMS\Page\PageTypeCode;
use TheliaCMS\Page\PublishedPage;

/**
 * The draft of a block, shown to whoever holds a signed link, inside the
 * default page template of the theme: a block has no page of its own, and
 * the screen that writes it still has to show it the way visitors will see
 * it on the pages that hold it.
 */
final readonly class CmsBlockPreviewController
{
    public function __construct(
        private PreviewLink $links,
        private CmsBlockRepository $blocks,
        private CmsPageRenderer $renderer,
    ) {
    }

    #[Route(
        '/cms-preview/block/{id}/{locale}/{expires}/{signature}',
        name: 'cms.block.preview',
        requirements: ['id' => '\d+', 'locale' => '[a-z]{2}_[A-Z]{2}', 'expires' => '\d+', 'signature' => '[a-f0-9]{64}'],
        methods: ['GET'],
    )]
    public function __invoke(int $id, string $locale, int $expires, string $signature): Response
    {
        if (!$this->links->isValidForBlock($id, $locale, $expires, $signature)) {
            throw new NotFoundHttpException();
        }

        $block = $this->blocks->findLive($id);

        if (!$block instanceof CmsBlock) {
            throw new NotFoundHttpException();
        }

        $content = $this->blocks->contentOf($block, $locale);
        $block->setLocale($locale);

        $response = new Response($this->renderer->render(new PublishedPage(
            id: $id,
            locale: $locale,
            title: trim((string) $block->getTitle()) ?: (string) $block->getCode(),
            pageType: PageTypeCode::orDefault(null),
            html: (string) $content?->getDraftHtml(),
            noindex: true,
            nofollow: true,
        )));

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
