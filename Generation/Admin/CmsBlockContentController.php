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

namespace TheliaCMS\Generation\Admin;

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
use TheliaCMS\Block\Admin\CmsBlockRepository;
use TheliaCMS\Block\Admin\CmsBlockWriter;
use TheliaCMS\Block\Admin\EmptyBlockContentException;
use TheliaCMS\Block\Item\ItemAddress;
use TheliaCMS\Block\Item\ItemBlockRepository;
use TheliaCMS\Block\Item\ItemTitle;
use TheliaCMS\Block\Item\ItemType;
use TheliaCMS\Content\Document;
use TheliaCMS\Generation\Brief;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\GenerationTarget;
use TheliaCMS\Model\CmsBlock;
use TheliaCMS\Model\CmsItemBlock;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Preview\PreviewLink;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;

/**
 * The content of a block, reusable or belonging to a catalogue item: the
 * same screen as a page, with the block told to the generator for what it
 * is. The bar of a block of an item leads back to the item.
 */
#[Route('/admin/cms/blocks/{id}/content', name: 'admin.cms.blocks.content.', requirements: ['id' => '\d+'])]
final readonly class CmsBlockContentController
{
    public function __construct(
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private CmsBlockRepository $blocks,
        private CmsBlockWriter $writer,
        private EditLanguage $languages,
        private ItemBlockRepository $itemBlocks,
        private ItemTitle $itemTitles,
        private ItemAddress $itemAddresses,
        private PreviewLink $previewLinks,
        private ContentScreen $screen,
        private GenerationRunner $runner,
    ) {
    }

    #[Route('', name: 'edit', methods: ['GET'])]
    public function edit(Request $request, int $id): Response
    {
        $block = $this->liveBlockOrFail($id);
        $lang = $this->languages->resolve($request);

        return new Response($this->screen->render($request, $this->subject($block, $lang)));
    }

    #[Route('/generate', name: 'generate', methods: ['POST'])]
    public function generate(Request $request, int $id): Response
    {
        $block = $this->liveBlockOrFail($id);
        $lang = $this->languages->resolve($request);
        $this->denyUnlessMayGenerate();

        $form = $this->forms->create(BriefType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->backWithErrors($request, $form, $block, $lang);
        }

        $data = $form->getData();
        $brief = new Brief((string) $data['text'], self::blankToNull($data['tone'] ?? null), self::blankToNull($data['audience'] ?? null));

        return $this->run($request, $block, $lang, GenerationRequest::fromBrief($this->target($block, $lang), $lang->getLocale(), $brief));
    }

    #[Route('/revise', name: 'revise', methods: ['POST'])]
    public function revise(Request $request, int $id): Response
    {
        $block = $this->liveBlockOrFail($id);
        $lang = $this->languages->resolve($request);
        $this->denyUnlessMayGenerate();

        $form = $this->forms->create(ChangeType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->backWithErrors($request, $form, $block, $lang);
        }

        $current = $this->documentOrFail($request, $block, $lang);

        if (!$current instanceof Document) {
            return $this->back($block, $lang);
        }

        return $this->run($request, $block, $lang, GenerationRequest::revision($this->target($block, $lang), $lang->getLocale(), $current, new Brief((string) $form->getData()['text'])));
    }

    #[Route('/section', name: 'section', methods: ['POST'])]
    public function section(Request $request, int $id): Response
    {
        $block = $this->liveBlockOrFail($id);
        $lang = $this->languages->resolve($request);
        $this->denyUnlessMayGenerate();

        $form = $this->forms->createNamed(ContentScreen::postedSectionForm($request), ChangeType::class, null, ['section' => true]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->backWithErrors($request, $form, $block, $lang);
        }

        $current = $this->documentOrFail($request, $block, $lang);

        if (!$current instanceof Document) {
            return $this->back($block, $lang);
        }

        $data = $form->getData();

        try {
            $generation = GenerationRequest::section($this->target($block, $lang), $lang->getLocale(), $current, (string) $data['section'], new Brief((string) $data['text']));
        } catch (\InvalidArgumentException) {
            $this->flash($request, 'danger', $this->translate('This section is no longer in the block: reload the screen.'));

            return $this->back($block, $lang);
        }

        return $this->run($request, $block, $lang, $generation);
    }

    #[Route('/publish', name: 'publish', methods: ['POST'])]
    public function publish(Request $request, int $id): Response
    {
        $block = $this->liveBlockOrFail($id);
        $lang = $this->languages->resolve($request);
        $this->denyUnlessMayPublish();

        try {
            $this->writer->publish($block, $lang->getLocale());
            $this->flash($request, 'success', $this->translate(null !== $this->itemBlocks->linkOf($block)
                ? 'The block is online on the page of its item.'
                : 'The block is online on every page using it.'));
        } catch (EmptyBlockContentException) {
            $this->flash($request, 'danger', $this->translate('This block has nothing in it yet: write it from a brief before publishing it.'));
        }

        return $this->back($block, $lang);
    }

    private function run(Request $request, CmsBlock $block, Lang $lang, GenerationRequest $generation): Response
    {
        $locale = $lang->getLocale();

        return $this->runner->respond(
            $request,
            $generation,
            fn (Document $document) => $this->writer->saveContent($block, $locale, $document),
            $this->screenUrl($block, $lang),
        );
    }

    private function subject(CmsBlock $block, Lang $lang): ContentSubject
    {
        $locale = $lang->getLocale();
        $block->setLocale($locale);
        $status = $this->blocks->statusOf($block, $locale);
        $parameters = ['id' => $block->getId(), EditLanguage::PARAMETER => $lang->getId()];
        $item = $this->itemOf($block, $locale);

        return new ContentSubject(
            target: $this->target($block, $lang),
            locale: $locale,
            languageId: (int) $lang->getId(),
            statusLabel: $status->label(),
            statusBadgeClass: $status->badgeClass(),
            backUrl: $item['url'] ?? $this->urls->generate('admin.cms.blocks.edit', $parameters),
            backLabel: null !== $item
                ? $this->translator->trans('Back to the %item%', ['%item%' => $this->translate($item['type']->label())], TheliaCMS::DOMAIN_NAME)
                : $this->translate('Back to settings'),
            draftPreviewUrl: $this->previewLinks->urlForBlock((int) $block->getId(), $locale),
            previewUrl: null,
            document: Document::fromJson($this->blocks->contentOf($block, $locale)?->getDraftDocument()),
            publishHint: $this->translate(null !== $item ? 'Publishing the block updates the page of its item.' : 'Publishing a block updates every page it appears on.'),
            routes: [
                'screen' => $this->screenUrl($block, $lang),
                'generate' => $this->urls->generate('admin.cms.blocks.content.generate', $parameters),
                'revise' => $this->urls->generate('admin.cms.blocks.content.revise', $parameters),
                'section' => $this->urls->generate('admin.cms.blocks.content.section', $parameters),
                'publish' => $this->urls->generate('admin.cms.blocks.content.publish', $parameters),
            ],
            testId: 'cms-block-content',
        );
    }

    private function target(CmsBlock $block, Lang $lang): GenerationTarget
    {
        $item = $this->itemOf($block, $lang->getLocale());

        return GenerationTarget::block(
            $block,
            $lang->getLocale(),
            null === $item ? null : \sprintf('%s "%s"', $this->translate($item['type']->label()), $item['title']),
        );
    }

    /**
     * The item a block belongs to: what to call the block, and where its
     * back button leads.
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

    private function documentOrFail(Request $request, CmsBlock $block, Lang $lang): ?Document
    {
        $document = Document::fromJson($this->blocks->contentOf($block, $lang->getLocale())?->getDraftDocument());

        if (!$document instanceof Document) {
            $this->flash($request, 'danger', $this->translate('This block holds nothing to change yet: write it from a brief first.'));
        }

        return $document;
    }

    private function backWithErrors(Request $request, FormInterface $form, CmsBlock $block, Lang $lang): RedirectResponse
    {
        foreach ($form->getErrors(true) as $error) {
            $this->flash($request, 'danger', (string) $error->getMessage());
        }

        if (!$form->isSubmitted()) {
            $this->flash($request, 'danger', $this->translate('Nothing was sent.'));
        }

        return $this->back($block, $lang);
    }

    private function back(CmsBlock $block, Lang $lang): RedirectResponse
    {
        return new RedirectResponse($this->screenUrl($block, $lang));
    }

    private function screenUrl(CmsBlock $block, Lang $lang): string
    {
        return $this->urls->generate('admin.cms.blocks.content.edit', ['id' => $block->getId(), EditLanguage::PARAMETER => $lang->getId()]);
    }

    private function liveBlockOrFail(int $id): CmsBlock
    {
        $block = $this->blocks->findLive($id);

        if (!$block instanceof CmsBlock) {
            throw new NotFoundHttpException();
        }

        return $block;
    }

    private function denyUnlessMayGenerate(): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::GENERATE], [], [AccessManager::UPDATE])) {
            throw new AccessDeniedHttpException($this->translate('You are not allowed to use the generator.'));
        }
    }

    private function denyUnlessMayPublish(): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [AccessManager::UPDATE])) {
            throw new AccessDeniedHttpException($this->translate('You are not allowed to change reusable blocks.'));
        }
    }

    private static function blankToNull(?string $value): ?string
    {
        return null === $value || '' === trim($value) ? null : trim($value);
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
