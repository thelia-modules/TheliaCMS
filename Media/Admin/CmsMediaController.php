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

namespace TheliaCMS\Media\Admin;

use OpenStudio\PageBuilderBundle\Service\GrapesJs\GrapesJsFileExtractor;
use OpenStudio\PageBuilderBundle\Service\GrapesJs\GrapesJsResponseBuilder;
use OpenStudio\PageBuilderBundle\Service\ImageLibraryService;
use OpenStudio\PageBuilderBundle\Service\ImageUploadOrchestrator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use TheliaLibrary\Model\LibraryImage;

/**
 * Image endpoints of the page builder.
 *
 * The bundle ships controllers of its own, but Thelia does not mount a
 * bundle's routes and they would sit outside `/admin`, where nothing guards
 * them. They are re-declared here, under the prefix CmsAdminGuard covers, and
 * carry the route names the page builder component looks up — that lookup is
 * how the bundle lets its host own these endpoints.
 */
final readonly class CmsMediaController
{
    public function __construct(
        private GrapesJsFileExtractor $fileExtractor,
        private ImageUploadOrchestrator $uploads,
        private GrapesJsResponseBuilder $responseBuilder,
        private ImageLibraryService $library,
        private SecurityContext $securityContext,
        private CmsMediaLibrary $cmsLibrary,
        private MediaUsageFinder $usages,
        private CmsMediaWriter $writer,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('/admin/cms/media/upload', name: 'openstudio_page_builder_image_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $this->denyUnless(AccessManager::CREATE);

        $files = $this->fileExtractor->extract($request);

        if ([] === $files) {
            return $this->responseBuilder->buildError('No files uploaded');
        }

        return $this->responseBuilder->buildFromResult($this->uploads->uploadAll(
            files: $files,
            context: $request->request->getString('context') ?: null,
            // The bundle controller never passes this one; the author of an
            // upload is worth recording.
            uploadedBy: (string) $this->securityContext->getAdminUser()?->getId(),
        ));
    }

    // `/admin/cms/media` itself is the media library screen; only the route
    // *name* matters to the bundle, which resolves these endpoints by name.
    #[Route('/admin/cms/media/images', name: 'openstudio_page_builder_image_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $context = $request->query->getString('context');

        if ('' === $context) {
            return new JsonResponse(['error' => 'Missing required parameter: context', 'data' => []], Response::HTTP_BAD_REQUEST);
        }

        $assets = array_map(
            static fn ($asset): array => $asset->toArray(),
            $this->library->getLibraryForContext($context),
        );

        return new JsonResponse(['data' => $assets]);
    }

    /**
     * Same rules as the media screen: only an image of the CMS, and never one
     * a page or a block still shows, which would leave a broken picture on the
     * site. The editor reads the refusal from `error`.
     */
    #[Route('/admin/cms/media/{id}', name: 'openstudio_page_builder_image_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $this->denyUnless(AccessManager::DELETE);

        $image = $this->cmsLibrary->ownedImage($id);

        if (!$image instanceof LibraryImage) {
            throw new NotFoundHttpException();
        }

        $usageCount = $this->usages->useCount($id);

        if ($usageCount > 0) {
            return new JsonResponse(['error' => $this->translator->trans(
                'This image is still used by %count% page(s) or block(s). Remove it from them first.',
                ['%count%' => $usageCount],
                TheliaCMS::DOMAIN_NAME,
            )], Response::HTTP_CONFLICT);
        }

        $this->writer->delete($image);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function denyUnless(string $access): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::MEDIA], [], [$access])) {
            throw new AccessDeniedHttpException('You are not allowed to change CMS media.');
        }
    }
}
