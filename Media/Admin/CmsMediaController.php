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
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use TheliaCMS\Security\CmsResources;

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

    #[Route('/admin/cms/media', name: 'openstudio_page_builder_image_list', methods: ['GET'])]
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

    #[Route('/admin/cms/media/{id}', name: 'openstudio_page_builder_image_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $this->denyUnless(AccessManager::DELETE);

        $this->library->delete($id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function denyUnless(string $access): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::MEDIA], [], [$access])) {
            throw new AccessDeniedHttpException('You are not allowed to change CMS media.');
        }
    }
}
