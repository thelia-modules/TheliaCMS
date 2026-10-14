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
use TheliaCMS\Block\Item\ItemAddress;
use TheliaCMS\Block\Item\ItemBlockRepository;
use TheliaCMS\Block\Item\ItemTitle;
use TheliaCMS\Block\Item\ItemType;
use TheliaCMS\Builder\Admin\CmsPageContentType;
use TheliaCMS\Builder\BlockCatalog;
use TheliaCMS\Builder\CmsBuilderConfig;
use TheliaCMS\Builder\InitialCanvas;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsItemBlock;
use TheliaCMS\Page\Admin\BuilderContent;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Partial\Definition\BlockPartial;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * The visual editor for a block, reusable or belonging to a catalogue item.
 *
 * The same builder as a page, with one thing left out on purpose: a block has
 * no SEO of its own (it is part of whichever page shows it). A reusable block
 * also offers no dynamic blocks — a block inside a block would have to be
 * resolved recursively, and a block that contains itself would never stop. The
 * block of an item is never placed inside another block, so it gets them all
 * but the reusable block itself, and the bar leads back to its item.
 */
#[Route('/admin/cms/blocks/{id}/builder', name: 'admin.cms.blocks.builder', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
final readonly class CmsBlockBuilderController
{
    private const string TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/blocks/builder.html.twig';

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private CmsBlockRepository $blocks,
        private CmsBlockWriter $writer,
        private CmsBuilderConfig $builderConfig,
        private BlockCatalog $catalog,
        private CmsSettings $settings,
        private EditLanguage $languages,
        private InitialCanvas $initialCanvas,
        private ItemBlockRepository $itemBlocks,
        private ItemTitle $itemTitles,
        private ItemAddress $itemAddresses,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $block = $this->blocks->findLive($id);

        if (!$block instanceof CmsBlock) {
            throw new NotFoundHttpException();
        }

        $lang = $this->languages->resolve($request);
        $locale = $lang->getLocale();
        $form = $this->buildForm($block, $locale);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->save($request, $block, $lang, $form);
        }

        $block->setLocale($locale);
        $content = $this->blocks->contentOf($block, $locale);

        $disabledBlocks = $this->settings->disabledBlocks();
        $item = $this->itemOf($block, $locale);

        return new Response($this->twig->render(self::TEMPLATE, [
            'form' => $form->createView(),
            'initial_html' => $this->initialCanvas->htmlFor($content?->getDraftProjectData(), $content?->getDraftHtml()),
            'block' => $block,
            'item' => $item,
            'builder_partials' => null !== $item ? $this->partialsForAnItemBlock() : null,
            'status' => $this->blocks->statusOf($block, $locale),
            'edit_locale' => $locale,
            'edit_language_id' => $lang->getId(),
            'builder_options' => $this->builderConfig->editorOptions(),
            'builder_canvas_wrapper_classes' => $this->builderConfig->canvasWrapperClasses(),
            'builder_labels' => $this->builderConfig->editorLabels(),
            'builder_catalog' => $this->catalog->toEditor($locale, $disabledBlocks),
            // The blocks of the editor itself the panel leaves out.
            'builder_disabled_blocks' => $disabledBlocks->ids(),
            'builder_locale' => substr($request->getLocale(), 0, 2),
            'allow_custom_code' => $this->securityContext->isGranted(['ADMIN'], [CmsResources::CUSTOM_CODE], [], [AccessManager::UPDATE]),
            'editor_version' => $this->builderConfig->editorVersion(),
        ]));
    }

    private function save(Request $request, CmsBlock $block, Lang $lang, FormInterface $form): Response
    {
        $this->denyUnlessUpdate();

        $data = $form->getData();
        $intent = $request->request->getString('save');

        $this->writer->saveContent($block, $lang->getLocale(), new BuilderContent(
            projectData: $data['projectData'],
            html: $data['html'],
            css: $data['css'],
        ));

        if ('autosave' === $intent) {
            return new JsonResponse(['saved' => true]);
        }

        if ('publish' === $intent) {
            try {
                $this->writer->publish($block, $lang->getLocale());
                $this->flash($request, 'success', null !== $this->itemBlocks->linkOf($block)
                    ? 'The block is online on the page of its item.'
                    : 'The block is online on every page using it.');
            } catch (EmptyBlockContentException) {
                $this->flash($request, 'danger', 'This block has nothing in it yet: add at least one element in the editor before publishing it.');
            }
        }

        return new RedirectResponse($this->urls->generate('admin.cms.blocks.builder', [
            'id' => $block->getId(),
            EditLanguage::PARAMETER => $lang->getId(),
        ]));
    }

    private function flash(Request $request, string $type, string $message): void
    {
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add($type, $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME));
        }
    }

    /**
     * The item a block belongs to, for the bar of the builder: what to call
     * the block, and where its back button leads.
     *
     * @return array{type: ItemType, id: int, title: string, url: string}|null
     */
    private function itemOf(CmsBlock $block, string $locale): ?array
    {
        $link = $this->itemBlocks->linkOf($block);

        if (!$link instanceof CmsItemBlock) {
            return null;
        }

        $type = ItemType::tryFrom((string) $link->getItemType());
        $itemId = (int) $link->getItemId();

        if (!$type instanceof ItemType) {
            return null;
        }

        return [
            'type' => $type,
            'id' => $itemId,
            'title' => $this->itemTitles->of($type, $itemId, $locale) ?? \sprintf('#%d', $itemId),
            'url' => $this->itemAddresses->adminUrl($type, $itemId),
        ];
    }

    /**
     * The dynamic blocks of a page, minus the reusable block: a block holding
     * a block is the recursion the reusable builder refuses.
     *
     * @return list<array<string, mixed>>
     */
    private function partialsForAnItemBlock(): array
    {
        return array_values(array_filter(
            $this->builderConfig->partials(),
            static fn (array $partial): bool => BlockPartial::NAME !== $partial['name'],
        ));
    }

    private function buildForm(CmsBlock $block, string $locale): FormInterface
    {
        $content = $this->blocks->contentOf($block, $locale);

        return $this->forms->create(CmsPageContentType::class, [
            'projectData' => $content?->getDraftProjectData(),
            'html' => $content?->getDraftHtml(),
            'css' => $content?->getDraftCss(),
        ]);
    }

    private function denyUnlessUpdate(): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [AccessManager::UPDATE])) {
            throw new AccessDeniedHttpException($this->translator->trans('You are not allowed to change reusable blocks.', [], TheliaCMS::DOMAIN_NAME));
        }
    }
}
