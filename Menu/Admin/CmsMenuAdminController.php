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

namespace TheliaCMS\Menu\Admin;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Lang;
use TheliaCMS\Menu\MenuTargetType;
use TheliaCMS\Model\CmsMenu;
use TheliaCMS\Model\CmsMenuItem;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * Menu back office: the list of menus, and one editing screen per menu holding
 * its tree and the form of a single entry.
 *
 * Like the page controller, this one resolves input, delegates every write to
 * CmsMenuWriter and redirects.
 */
#[Route('/admin/cms/menus', name: 'admin.cms.menus.')]
final readonly class CmsMenuAdminController
{
    private const string LIST_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/menus/list.html.twig';
    private const string EDIT_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/menus/edit.html.twig';

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private CmsMenuRepository $menus,
        private CmsMenuWriter $writer,
        private MenuTargetChoices $choices,
        private EditLanguage $languages,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $lang = $this->languages->resolve($request);

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'menus' => $this->menus->menus($lang->getLocale()),
            'edit_locale' => $lang->getLocale(),
            'edit_language_id' => $lang->getId(),
        ]));
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'], priority: 1)]
    public function create(Request $request): Response
    {
        $this->denyUnless(AccessManager::CREATE);

        $lang = $this->languages->resolve($request);
        $form = $this->forms->create(CmsMenuType::class, ['code' => null, 'title' => null], [
            'action' => $this->urls->generate('admin.cms.menus.create', [EditLanguage::PARAMETER => $lang->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $menu = new CmsMenu();

            if ($this->saveMenu($form, $menu, $lang)) {
                return $this->backToEdit((int) $menu->getId(), $lang);
            }
        }

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'menu' => null,
            'menu_form' => $form->createView(),
            'rows' => [],
            'item_form' => null,
            'edited_item' => null,
            'edit_locale' => $lang->getLocale(),
            'edit_language_id' => $lang->getId(),
            'may_write' => true,
            'place_url_template' => null,
            'assets_version' => null,
        ]));
    }

    #[Route('/{id}', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $menu = $this->menuOrFail($id);
        $lang = $this->languages->resolve($request);
        $form = $this->menuForm($menu, $lang);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->saveMenu($form, $menu, $lang)) {
            return $this->backToEdit($id, $lang);
        }

        return $this->screen($menu, $lang, $form);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::DELETE);

        $this->writer->deleteMenu($this->menuOrFail($id));

        return new RedirectResponse($this->urls->generate('admin.cms.menus.list', [
            EditLanguage::PARAMETER => $this->languages->resolve($request)->getId(),
        ]));
    }

    /**
     * Adds an entry, or saves the one being edited. Both live on the menu screen,
     * so both come back to it.
     */
    #[Route('/{id}/entries/{itemId}', name: 'entry_save', requirements: ['id' => '\d+', 'itemId' => '\d+|new'], methods: ['GET', 'POST'])]
    public function saveEntry(Request $request, int $id, string $itemId): Response
    {
        $menu = $this->menuOrFail($id);
        $lang = $this->languages->resolve($request);
        $item = 'new' === $itemId ? null : $this->itemOrFail($menu, (int) $itemId);

        $form = $this->itemForm($menu, $lang, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyUnless(null === $item ? AccessManager::CREATE : AccessManager::UPDATE);

            try {
                $this->writer->saveItem($menu, $item, $lang->getLocale(), $this->itemData($form));

                return $this->backToEdit($id, $lang);
            } catch (\DomainException $exception) {
                $form->addError(new FormError($this->translate($exception->getMessage())));
            }
        }

        return $this->screen($menu, $lang, null, $form, $item);
    }

    #[Route('/{id}/entries/{itemId}/delete', name: 'entry_delete', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['POST'])]
    public function deleteEntry(Request $request, int $id, int $itemId): Response
    {
        $this->denyUnless(AccessManager::DELETE);

        $menu = $this->menuOrFail($id);
        $this->writer->deleteItem($menu, $this->itemOrFail($menu, $itemId));

        return $this->backToEdit($id, $this->languages->resolve($request));
    }

    #[Route('/{id}/entries/{itemId}/move/{direction}', name: 'entry_move', requirements: ['id' => '\d+', 'itemId' => '\d+', 'direction' => 'up|down'], methods: ['POST'])]
    public function moveEntry(Request $request, int $id, int $itemId, string $direction): Response
    {
        $this->denyUnless(AccessManager::UPDATE);

        $menu = $this->menuOrFail($id);
        $this->writer->move($menu, $this->itemOrFail($menu, $itemId), 'up' === $direction ? -1 : 1);

        return $this->backToEdit($id, $this->languages->resolve($request));
    }

    /**
     * Where a dragged row landed. Answers JSON because the editor calls it in
     * the background; the screen is reloaded by the browser afterwards, so the
     * server stays the only source of truth for the tree.
     */
    #[Route('/{id}/entries/{itemId}/place', name: 'entry_place', requirements: ['id' => '\d+', 'itemId' => '\d+'], methods: ['POST'])]
    public function placeEntry(Request $request, int $id, int $itemId): JsonResponse
    {
        $this->denyUnless(AccessManager::UPDATE);

        $menu = $this->menuOrFail($id);
        $item = $this->itemOrFail($menu, $itemId);

        try {
            $this->writer->place(
                $menu,
                $item,
                $request->request->getInt('parent'),
                $request->request->getInt('position', 1),
            );
        } catch (\DomainException $exception) {
            return new JsonResponse(['error' => $this->translate($exception->getMessage())], Response::HTTP_CONFLICT);
        }

        return new JsonResponse(['ok' => true]);
    }

    private function screen(
        CmsMenu $menu,
        Lang $lang,
        ?FormInterface $menuForm = null,
        ?FormInterface $itemForm = null,
        ?CmsMenuItem $editedItem = null,
    ): Response {
        $locale = $lang->getLocale();

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'menu' => $menu,
            'menu_form' => ($menuForm ?? $this->menuForm($menu, $lang))->createView(),
            'rows' => $this->menus->rows($menu, $locale),
            'item_form' => ($itemForm ?? $this->itemForm($menu, $lang, null))->createView(),
            'edited_item' => $editedItem,
            'edit_locale' => $locale,
            'edit_language_id' => $lang->getId(),
            'may_write' => $this->securityContext->isGranted(['ADMIN'], [CmsResources::MENU], [], [AccessManager::UPDATE]),
            'place_url_template' => $this->placeUrlTemplate($menu, $lang),
            'assets_version' => $this->assetsVersion(),
        ]));
    }

    private function menuForm(CmsMenu $menu, Lang $lang): FormInterface
    {
        return $this->forms->create(CmsMenuType::class, [
            'code' => $menu->getCode(),
            'title' => $menu->setLocale($lang->getLocale())->getTitle(),
        ], [
            // Explicit, because this form is also rendered on the screen that
            // edits a single entry, whose URL it must not post to.
            'action' => $this->urls->generate('admin.cms.menus.edit', [
                'id' => $menu->getId(),
                EditLanguage::PARAMETER => $lang->getId(),
            ]),
        ]);
    }

    /**
     * The address the editor posts a dragged row to, with a placeholder where
     * the entry identifier goes.
     *
     * The route requires digits, so the URL is generated for a valid identifier
     * and the segment is then swapped: generating it with `{id}` in place would
     * simply fail to match.
     */
    private function placeUrlTemplate(CmsMenu $menu, Lang $lang): string
    {
        $generated = $this->urls->generate('admin.cms.menus.entry_place', [
            'id' => $menu->getId(),
            'itemId' => 0,
            EditLanguage::PARAMETER => $lang->getId(),
        ]);

        return str_replace('/entries/0/place', '/entries/{id}/place', $generated);
    }

    /**
     * Changes whenever the editor script does: `module_asset()` returns a stable
     * URL, so without it a browser keeps running the copy it already holds.
     */
    private function assetsVersion(): string
    {
        $script = __DIR__.'/../../templates/backOffice/default-twig/assets/menu-editor.js';

        return substr(hash('xxh3', (string) @filemtime($script)), 0, 12);
    }

    private function itemForm(CmsMenu $menu, Lang $lang, ?CmsMenuItem $item): FormInterface
    {
        $locale = $lang->getLocale();
        $type = MenuTargetType::fromStorage($item?->getTargetType());
        $targetId = null === $item ? null : (int) $item->getTargetId();

        return $this->forms->create(CmsMenuItemType::class, [
            'targetType' => null === $item ? MenuTargetType::Page->value : $type->value,
            'pageId' => MenuTargetType::Page === $type ? $targetId : null,
            'contentId' => MenuTargetType::Content === $type ? $targetId : null,
            'folderId' => MenuTargetType::Folder === $type ? $targetId : null,
            'url' => $item?->getUrl(),
            'label' => null === $item ? null : $item->setLocale($locale)->getLabel(),
            'parent' => (int) $item?->getParent(),
            'openNewTab' => 1 === $item?->getOpenNewTab(),
        ], [
            'page_choices' => $this->choices->pages($locale),
            'content_choices' => $this->choices->contents($locale),
            'folder_choices' => $this->choices->folders($locale),
            'parent_choices' => $this->menus->parentChoices($menu, $locale, null === $item ? null : (int) $item->getId()),
            'action' => $this->urls->generate('admin.cms.menus.entry_save', [
                'id' => $menu->getId(),
                'itemId' => $item?->getId() ?? 'new',
                EditLanguage::PARAMETER => $lang->getId(),
            ]),
        ]);
    }

    private function itemData(FormInterface $form): MenuItemData
    {
        $data = $form->getData();
        $type = MenuTargetType::fromStorage($data['targetType']);

        $targetId = match ($type) {
            MenuTargetType::Page => $data['pageId'],
            MenuTargetType::Content => $data['contentId'],
            MenuTargetType::Folder => $data['folderId'],
            default => null,
        };

        $label = trim((string) $data['label']);

        return new MenuItemData(
            type: $type,
            targetId: null === $targetId ? null : (int) $targetId,
            url: $data['url'],
            label: '' === $label ? null : $label,
            parent: (int) $data['parent'],
            openNewTab: (bool) $data['openNewTab'],
        );
    }

    private function saveMenu(FormInterface $form, CmsMenu $menu, Lang $lang): bool
    {
        $this->denyUnless($menu->isNew() ? AccessManager::CREATE : AccessManager::UPDATE);

        $data = $form->getData();

        try {
            $this->writer->saveMenu($menu, $lang->getLocale(), (string) $data['code'], (string) $data['title']);
        } catch (\DomainException $exception) {
            $form->get('code')->addError(new FormError($this->translate($exception->getMessage())));

            return false;
        }

        return true;
    }

    private function menuOrFail(int $id): CmsMenu
    {
        $menu = $this->menus->find($id);

        if (!$menu instanceof CmsMenu) {
            throw new NotFoundHttpException();
        }

        return $menu;
    }

    private function itemOrFail(CmsMenu $menu, int $itemId): CmsMenuItem
    {
        $item = $this->menus->findItem($menu, $itemId);

        if (!$item instanceof CmsMenuItem) {
            throw new NotFoundHttpException();
        }

        return $item;
    }

    private function denyUnless(string $access): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::MENU], [], [$access])) {
            throw new AccessDeniedHttpException($this->translate('You are not allowed to change menus.'));
        }
    }

    private function translate(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }

    private function backToEdit(int $id, Lang $lang): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate('admin.cms.menus.edit', [
            'id' => $id,
            EditLanguage::PARAMETER => $lang->getId(),
        ]));
    }
}
