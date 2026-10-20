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

namespace TheliaCMS\Block\Item\Admin;

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
use TheliaCMS\Block\Item\ItemAddress;
use TheliaCMS\Block\Item\ItemBlockRepository;
use TheliaCMS\Block\Item\ItemBlockWriter;
use TheliaCMS\Block\Item\ItemTitle;
use TheliaCMS\Block\Item\ItemType;
use TheliaCMS\Block\Item\ItemTypes;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;

/**
 * The two actions of the block card on a catalogue screen: open the block in
 * the builder, creating it on the way when the item has none yet, and delete
 * it. Both are writes, so both are posts carrying the token of the back office,
 * checked by the guard of `/admin/cms`.
 */
#[Route('/admin/cms/item-blocks/{type}/{itemId}', name: 'admin.cms.item_blocks.', requirements: ['type' => 'product|category|brand|dealer', 'itemId' => '\d+'])]
final readonly class CmsItemBlockController
{
    public function __construct(
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private ItemTypes $types,
        private ItemTitle $titles,
        private ItemAddress $addresses,
        private ItemBlockRepository $itemBlocks,
        private ItemBlockWriter $writer,
        private EditLanguage $languages,
    ) {
    }

    #[Route('/edit', name: 'edit', methods: ['POST'])]
    public function edit(Request $request, string $type, int $itemId): Response
    {
        $itemType = $this->itemTypeOrFail($type, $itemId);
        $lang = $this->languages->resolve($request);

        // Creating a block is creating content; opening an existing one in
        // the builder is changing it. The builder checks UPDATE again on save.
        $this->denyUnless(null === $this->itemBlocks->blockFor($itemType, $itemId) ? AccessManager::CREATE : AccessManager::UPDATE);

        $block = $this->writer->blockFor($itemType, $itemId, $lang->getLocale());

        return new RedirectResponse($this->urls->generate('admin.cms.blocks.content.edit', [
            'id' => $block->getId(),
            EditLanguage::PARAMETER => $lang->getId(),
        ]));
    }

    #[Route('/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, string $type, int $itemId): Response
    {
        // The item may be gone already: a block left behind by a row deleted
        // outside the back office is still deleted from here.
        $itemType = $this->itemTypeOrFail($type, $itemId, mustExist: false);

        $this->denyUnless(AccessManager::DELETE);

        $this->writer->deleteFor($itemType, $itemId);

        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('success', $this->translator->trans('The block of this item is deleted.', [], TheliaCMS::DOMAIN_NAME));
        }

        return new RedirectResponse($this->addresses->adminUrl($itemType, $itemId));
    }

    private function itemTypeOrFail(string $type, int $itemId, bool $mustExist = true): ItemType
    {
        $itemType = ItemType::tryFrom($type);

        if (!$itemType instanceof ItemType || !$this->types->isAvailable($itemType)) {
            throw new NotFoundHttpException();
        }

        if ($mustExist && !$this->titles->exists($itemType, $itemId)) {
            throw new NotFoundHttpException();
        }

        return $itemType;
    }

    private function denyUnless(string $access): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [$access])) {
            throw new AccessDeniedHttpException($this->translator->trans('You are not allowed to change the blocks of catalogue items.', [], TheliaCMS::DOMAIN_NAME));
        }
    }
}
