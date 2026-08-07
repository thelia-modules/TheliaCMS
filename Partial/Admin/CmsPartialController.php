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

namespace TheliaCMS\Partial\Admin;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Model\FolderQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Partial\MissingPartialPropException;
use TheliaCMS\Partial\PartialRenderer;
use TheliaCMS\Partial\UnknownPartialException;

/**
 * Serves the editor what it cannot know on its own about dynamic blocks: how
 * one of them looks, and what an editor may pick in its settings.
 *
 * Everything here sits under `/admin/cms`, which `CmsAdminGuard` closes to
 * anyone who is not an authenticated administrator holding the CMS pages
 * resource — the rendering endpoint executes templates and the source
 * endpoints list back-office records, neither of which is public.
 */
final readonly class CmsPartialController
{
    public function __construct(
        private PartialRenderer $partials,
    ) {
    }

    /**
     * Renders one block as it will appear on the site, for the preview shown in
     * the canvas.
     *
     * The name is resolved against the registry — the editor cannot ask for a
     * template by path — and the fragment cache is bypassed, so an author sees
     * the setting they have just changed rather than the previous answer.
     */
    #[Route('/admin/cms/partials/render', name: 'admin.cms.partials.render', methods: ['POST'])]
    public function render(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);
        $payload = \is_array($payload) ? $payload : [];

        $name = \is_string($payload['name'] ?? null) ? $payload['name'] : '';
        $props = \is_array($payload['props'] ?? null) ? $payload['props'] : [];

        try {
            $html = $this->partials->renderOne($name, $props, $this->localeOf($payload), cache: false);
        } catch (UnknownPartialException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (MissingPartialPropException $exception) {
            // Not an error: a block just dropped on the page has no settings
            // yet. The editor shows the sentence telling the author what to
            // pick, and the browser console stays clean.
            return new JsonResponse(['html' => '', 'notice' => $exception->getMessage()]);
        } catch (\Throwable $throwable) {
            return new JsonResponse(['error' => $throwable->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(['html' => $html]);
    }

    /**
     * Folders an editor may point a news block at.
     */
    #[Route('/admin/cms/partials/sources/folders', name: 'admin.cms.partials.sources.folders', methods: ['GET'])]
    public function folders(Request $request): Response
    {
        $locale = $request->getLocale();
        $folders = FolderQuery::create()->filterByVisible(1)->orderByPosition()->find();

        return new JsonResponse(array_map(
            static function ($folder) use ($locale): array {
                $folder->setLocale($locale);

                return ['id' => (int) $folder->getId(), 'name' => (string) $folder->getTitle()];
            },
            iterator_to_array($folders, false),
        ));
    }

    /**
     * Menus an editor may drop into a page.
     */
    #[Route('/admin/cms/partials/sources/menus', name: 'admin.cms.partials.sources.menus', methods: ['GET'])]
    public function menus(Request $request): Response
    {
        $locale = $request->getLocale();
        $menus = CmsMenuQuery::create()->orderByCode()->find();

        return new JsonResponse(array_map(
            static function ($menu) use ($locale): array {
                $menu->setLocale($locale);

                $title = trim((string) $menu->getTitle());

                return ['id' => (int) $menu->getId(), 'name' => '' === $title ? (string) $menu->getCode() : $title];
            },
            iterator_to_array($menus, false),
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function localeOf(array $payload): string
    {
        $locale = \is_string($payload['locale'] ?? null) ? $payload['locale'] : '';

        // Anything the browser sends is checked against the languages of the
        // shop rather than trusted as a locale.
        if (null !== LangQuery::create()->filterByLocale($locale)->findOne()) {
            return $locale;
        }

        return Lang::getDefaultLanguage()->getLocale();
    }
}
