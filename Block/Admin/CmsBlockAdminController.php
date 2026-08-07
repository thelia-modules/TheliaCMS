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

namespace TheliaCMS\Block\Admin;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
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
use TheliaCMS\Block\BlockUsageFinder;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * Reusable blocks in the back office: the list, and the settings of one block.
 *
 * Its content is laid out in the page builder, on a screen of its own, exactly
 * like a page.
 */
#[Route('/admin/cms/blocks', name: 'admin.cms.blocks.')]
final readonly class CmsBlockAdminController
{
    private const string LIST_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/blocks/list.html.twig';
    private const string EDIT_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/blocks/edit.html.twig';

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private CmsBlockRepository $blocks,
        private CmsBlockWriter $writer,
        private BlockUsageFinder $usages,
        private EditLanguage $languages,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $lang = $this->languages->resolve($request);

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'blocks' => $this->blocks->all($lang->getLocale()),
            'edit_locale' => $lang->getLocale(),
            'edit_language_id' => $lang->getId(),
            'may_write' => $this->isGranted(AccessManager::UPDATE),
        ]));
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'], priority: 1)]
    public function create(Request $request): Response
    {
        $this->denyUnless(AccessManager::CREATE);

        $lang = $this->languages->resolve($request);
        $form = $this->forms->create(CmsBlockType::class, ['code' => null, 'title' => null], [
            'action' => $this->urls->generate('admin.cms.blocks.create', [EditLanguage::PARAMETER => $lang->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $block = new CmsBlock();

            if ($this->saveBlock($form, $block, $lang)) {
                // Straight to the builder: a block with no content is not
                // something anyone wants to look at a second form for.
                return new RedirectResponse($this->urls->generate('admin.cms.blocks.builder', [
                    'id' => $block->getId(),
                    EditLanguage::PARAMETER => $lang->getId(),
                ]));
            }
        }

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'block' => null,
            'block_form' => $form->createView(),
            'status' => null,
            'usages' => [],
            'edit_locale' => $lang->getLocale(),
            'edit_language_id' => $lang->getId(),
            'may_write' => true,
        ]));
    }

    #[Route('/{id}', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $block = $this->blockOrFail($id);
        $lang = $this->languages->resolve($request);
        $block->setLocale($lang->getLocale());

        $form = $this->forms->create(CmsBlockType::class, [
            'code' => $block->getCode(),
            'title' => $block->getTitle(),
        ], [
            'action' => $this->urls->generate('admin.cms.blocks.edit', ['id' => $id, EditLanguage::PARAMETER => $lang->getId()]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->saveBlock($form, $block, $lang)) {
            return new RedirectResponse($this->urls->generate('admin.cms.blocks.edit', [
                'id' => $id,
                EditLanguage::PARAMETER => $lang->getId(),
            ]));
        }

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'block' => $block,
            'block_form' => $form->createView(),
            'status' => $this->blocks->statusOf($block, $lang->getLocale()),
            'usages' => $this->usages->pagesUsing($id),
            'edit_locale' => $lang->getLocale(),
            'edit_language_id' => $lang->getId(),
            'may_write' => $this->isGranted(AccessManager::UPDATE),
        ]));
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::DELETE);

        $lang = $this->languages->resolve($request);

        try {
            $this->writer->delete($this->blockOrFail($id));
        } catch (\DomainException $exception) {
            $this->flash($request, 'danger', $this->translate($exception->getMessage()));

            return new RedirectResponse($this->urls->generate('admin.cms.blocks.edit', [
                'id' => $id,
                EditLanguage::PARAMETER => $lang->getId(),
            ]));
        }

        return new RedirectResponse($this->urls->generate('admin.cms.blocks.list', [
            EditLanguage::PARAMETER => $lang->getId(),
        ]));
    }

    private function saveBlock(FormInterface $form, CmsBlock $block, Lang $lang): bool
    {
        $data = $form->getData();
        $code = (string) $data['code'];

        if ($this->blocks->codeIsTaken($code, $block->isNew() ? null : (int) $block->getId())) {
            $form->get('code')->addError(new FormError($this->translate('Another block already uses this code.')));

            return false;
        }

        $this->writer->save($block, $lang->getLocale(), $code, (string) $data['title']);

        return true;
    }

    private function blockOrFail(int $id): CmsBlock
    {
        $block = $this->blocks->findLive($id);

        if (!$block instanceof CmsBlock) {
            throw new NotFoundHttpException();
        }

        return $block;
    }

    private function isGranted(string $access): bool
    {
        return $this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [$access]);
    }

    private function denyUnless(string $access): void
    {
        if (!$this->isGranted($access)) {
            throw new AccessDeniedHttpException($this->translate('You are not allowed to change reusable blocks.'));
        }
    }

    private function flash(Request $request, string $type, string $message): void
    {
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add($type, $message);
        }
    }

    private function translate(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
