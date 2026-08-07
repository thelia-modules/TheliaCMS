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

namespace TheliaCMS\Builder\Admin;

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
use TheliaCMS\Builder\BlockCatalog;
use TheliaCMS\Builder\CmsBuilderConfig;
use TheliaCMS\Builder\HeadingChecker;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContent;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Page\Admin\BuilderContent;
use TheliaCMS\Page\Admin\CmsPageAdminRepository;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Page\Admin\EmptyPageContentException;
use TheliaCMS\Preview\PreviewLink;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * The visual editor, on a screen of its own.
 *
 * A page builder needs the whole window: it lives apart from the settings
 * screen rather than inside one of its cards.
 */
#[Route('/admin/cms/pages/{id}/builder', name: 'admin.cms.pages.builder', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
final readonly class CmsPageBuilderController
{
    private const string TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/pages/builder.html.twig';

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private CmsPageAdminRepository $pages,
        private CmsPageWriter $writer,
        private CmsBuilderConfig $builderConfig,
        private BlockCatalog $catalog,
        private EditLanguage $languages,
        private HeadingChecker $headings,
        private PreviewLink $previewLinks,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        $page = $this->pages->findLive($id);

        if (!$page instanceof CmsPage) {
            throw new NotFoundHttpException();
        }

        $lang = $this->languages->resolve($request);
        $locale = $lang->getLocale();
        $form = $this->buildForm($page, $locale);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->save($request, $page, $lang, $form);
        }

        $page->setLocale($locale);

        $content = $this->contentOf($page, $locale);

        return new Response($this->twig->render(self::TEMPLATE, [
            'form' => $form->createView(),
            // A page written before the builder existed — a seeded legal page,
            // an import — has HTML but no GrapesJS project. Handing that HTML
            // to the canvas is what keeps opening the editor from wiping it.
            'initial_html' => null === $content?->getDraftProjectData() ? $content?->getDraftHtml() : null,
            'page' => $page,
            'status' => $this->pages->statusOf($page, $locale),
            'edit_locale' => $locale,
            'edit_language_id' => $lang->getId(),
            'preview_url' => $page->getUrl($locale),
            // Shareable, expiring link on the draft: the client reviewing a
            // page has no back-office account.
            'draft_preview_url' => $this->previewLinks->urlFor($id, $locale),
            'builder_options' => $this->builderConfig->editorOptions(),
            // Server-rendered blocks the editor may offer, with their settings.
            'builder_partials' => $this->builderConfig->partials(),
            // The starting blocks, with their sample text in the language of
            // the page rather than of the back office.
            'builder_catalog' => $this->catalog->toEditor($locale),
            // The editor speaks the language the back office is displayed in,
            // which is not the language of the page being translated.
            'builder_locale' => substr($request->getLocale(), 0, 2),
            // Free HTML is an authorisation, not a preference: the plugin stays
            // out of the editor for anyone without the resource.
            'allow_custom_code' => $this->securityContext->isGranted(['ADMIN'], [CmsResources::CUSTOM_CODE], [], [AccessManager::UPDATE]),
            'editor_version' => $this->builderConfig->editorVersion(),
        ]));
    }

    private function save(Request $request, CmsPage $page, Lang $lang, FormInterface $form): Response
    {
        $this->denyUnlessUpdate();

        $data = $form->getData();
        $intent = $request->request->getString('save');

        $this->writer->saveContent($page, $lang->getLocale(), new BuilderContent(
            projectData: $data['projectData'],
            html: $data['html'],
            css: $data['css'],
        ));

        // The autosave posts the same form in the background; answering with a
        // redirect would send the editor's fetch chasing a full page.
        if ('autosave' === $intent) {
            return new JsonResponse(['saved' => true]);
        }

        // Publishing always follows a save, never replaces it: the button would
        // otherwise put the previously stored draft online.
        if ('publish' === $intent) {
            $this->publish($request, $page, $lang);
        }

        return new RedirectResponse($this->urls->generate('admin.cms.pages.builder', [
            'id' => $page->getId(),
            EditLanguage::PARAMETER => $lang->getId(),
        ]));
    }

    /**
     * Publishing runs the accessibility check first. In `warn` mode the page
     * still goes online and the editor is told what to fix; in `block` mode it
     * does not — a heading structure is not something a visitor using a screen
     * reader can work around.
     */
    private function publish(Request $request, CmsPage $page, Lang $lang): void
    {
        $problems = $this->headings->check($this->draftHtmlOf($page, $lang->getLocale()));
        $blocking = [] !== $problems && 'block' === TheliaCMS::getConfigValue('heading_check_mode', 'warn');

        foreach ($problems as $problem) {
            $this->flash($request, $blocking ? 'danger' : 'warning', $problem);
        }

        if ($blocking) {
            $this->flash($request, 'danger', $this->translator->trans('The page was not published: fix the heading structure first.', [], TheliaCMS::DOMAIN_NAME));

            return;
        }

        try {
            $this->writer->publish($page, $lang->getLocale());
        } catch (EmptyPageContentException) {
            $this->flash($request, 'danger', $this->translator->trans('This page has no content yet: add at least one block in the editor before publishing it.', [], TheliaCMS::DOMAIN_NAME));
        }
    }

    private function draftHtmlOf(CmsPage $page, string $locale): ?string
    {
        return $this->contentOf($page, $locale)?->getDraftHtml();
    }

    private function flash(Request $request, string $type, string $message): void
    {
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add($type, $message);
        }
    }

    private function contentOf(CmsPage $page, string $locale): ?CmsPageContent
    {
        return CmsPageContentQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($locale)
            ->findOne();
    }

    private function buildForm(CmsPage $page, string $locale): FormInterface
    {
        $content = $this->contentOf($page, $locale);

        return $this->forms->create(CmsPageContentType::class, [
            'projectData' => $content?->getDraftProjectData(),
            'html' => $content?->getDraftHtml(),
            'css' => $content?->getDraftCss(),
        ]);
    }

    private function denyUnlessUpdate(): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [AccessManager::UPDATE])) {
            throw new AccessDeniedHttpException($this->translator->trans('You are not allowed to change CMS pages.', [], 'theliacms'));
        }
    }
}
