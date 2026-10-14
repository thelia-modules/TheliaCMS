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

namespace TheliaCMS\Block\Item;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\Lang;
use TheliaCMS\Block\Admin\CmsBlockRepository;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * The block card on the screen of a catalogue item.
 *
 * On a product, a category and a brand it sits in the right column, under
 * the cards of the other modules. The store screen of the Dealer module
 * offers no column, only extra tabs, so the card is a tab there.
 *
 * Shown to whoever may see CMS pages; the buttons go to whoever may write
 * them, and the controller checks again.
 */
class ItemBlockHook extends BaseHook
{
    private const string TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/blocks/_item_card.html.twig';

    public function __construct(
        private readonly SecurityContext $securityContext,
        private readonly UrlGeneratorInterface $urls,
        private readonly Environment $twig,
        private readonly ItemTypes $types,
        private readonly ItemBlockRepository $itemBlocks,
        private readonly CmsBlockRepository $blocks,
        private readonly EditLanguage $languages,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'product.modification.form-right.bottom' => [
                ['type' => 'back', 'method' => 'onProductRightColumn'],
            ],
            'category.modification.form-right.bottom' => [
                ['type' => 'back', 'method' => 'onCategoryRightColumn'],
            ],
            'brand.modification.form-right.bottom' => [
                ['type' => 'back', 'method' => 'onBrandRightColumn'],
            ],
            // Not `dealer.tab`: the Dealer module named its tab hook that way.
            'dealer.additional' => [
                ['type' => 'back', 'method' => 'onDealerTab'],
            ],
        ];
    }

    public function onProductRightColumn(HookRenderEvent $event): void
    {
        $this->addCard($event, ItemType::Product, $event->getArgument('product_id'));
    }

    public function onCategoryRightColumn(HookRenderEvent $event): void
    {
        $this->addCard($event, ItemType::Category, $event->getArgument('category_id'));
    }

    public function onBrandRightColumn(HookRenderEvent $event): void
    {
        $this->addCard($event, ItemType::Brand, $event->getArgument('brand_id'));
    }

    /**
     * The store screen passes the store as `dealer`, not `id`.
     */
    public function onDealerTab(HookRenderBlockEvent $event): void
    {
        $itemId = (int) $event->getArgument('dealer');

        if ($itemId <= 0 || !$this->maySee(ItemType::Dealer)) {
            return;
        }

        $event->add([
            'id' => 'cms-item-block',
            'title' => $this->trans('Content block', [], TheliaCMS::DOMAIN_NAME),
            'content' => $this->renderCard(ItemType::Dealer, $itemId),
        ]);
    }

    private function addCard(HookRenderEvent $event, ItemType $type, mixed $itemId): void
    {
        $itemId = (int) $itemId;

        if ($itemId <= 0 || !$this->maySee($type)) {
            return;
        }

        $event->add($this->renderCard($type, $itemId));
    }

    private function maySee(ItemType $type): bool
    {
        return $this->types->isAvailable($type)
            && $this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [AccessManager::VIEW]);
    }

    private function renderCard(ItemType $type, int $itemId): string
    {
        $lang = $this->editLanguage();
        $block = $this->itemBlocks->blockFor($type, $itemId);

        return $this->twig->render(self::TEMPLATE, [
            'item_type' => $type,
            'item_id' => $itemId,
            'block' => $block,
            'status' => $block instanceof CmsBlock ? $this->blocks->statusOf($block, $lang->getLocale()) : null,
            'edit_language_id' => $lang->getId(),
            'may_write' => $this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [AccessManager::UPDATE]),
            'may_delete' => $this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [AccessManager::DELETE]),
            'edit_url' => $this->urls->generate('admin.cms.item_blocks.edit', ['type' => $type->value, 'itemId' => $itemId, EditLanguage::PARAMETER => $lang->getId()]),
            'delete_url' => $this->urls->generate('admin.cms.item_blocks.delete', ['type' => $type->value, 'itemId' => $itemId]),
            'builder_url' => $block instanceof CmsBlock ? $this->urls->generate('admin.cms.blocks.builder', ['id' => $block->getId(), EditLanguage::PARAMETER => $lang->getId()]) : null,
        ]);
    }

    /**
     * The language the catalogue screen is editing: its `edit_language_id`
     * parameter, else the one kept in the session, the way the CMS screens
     * read it.
     */
    private function editLanguage(): Lang
    {
        $request = $this->getRequest();

        return null !== $request ? $this->languages->resolve($request) : Lang::getDefaultLanguage();
    }
}
