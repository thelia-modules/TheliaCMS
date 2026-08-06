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
use TheliaCMS\Builder\CmsBuilderConfig;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Page\Admin\BuilderContent;
use TheliaCMS\Page\Admin\CmsPageAdminRepository;
use TheliaCMS\Page\Admin\CmsPageWriter;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Security\CmsResources;
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
        private EditLanguage $languages,
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

        return new Response($this->twig->render(self::TEMPLATE, [
            'form' => $form->createView(),
            'page' => $page,
            'status' => $this->pages->statusOf($page, $locale),
            'edit_locale' => $locale,
            'edit_language_id' => $lang->getId(),
            'builder_options' => $this->builderConfig->editorOptions(),
            // The editor speaks the language the back office is displayed in,
            // which is not the language of the page being translated.
            'builder_locale' => substr($request->getLocale(), 0, 2),
            // Free HTML is an authorisation, not a preference: the plugin stays
            // out of the editor for anyone without the resource.
            'allow_custom_code' => $this->securityContext->isGranted(['ADMIN'], [CmsResources::CUSTOM_CODE], [], [AccessManager::UPDATE]),
        ]));
    }

    private function save(Request $request, CmsPage $page, Lang $lang, FormInterface $form): RedirectResponse
    {
        $this->denyUnlessUpdate();

        $data = $form->getData();

        $this->writer->saveContent($page, $lang->getLocale(), new BuilderContent(
            projectData: $data['projectData'],
            html: $data['html'],
            css: $data['css'],
        ));

        // Publishing always follows a save, never replaces it: the button would
        // otherwise put the previously stored draft online.
        if ('publish' === $request->request->getString('save')) {
            $this->writer->publish($page, $lang->getLocale());
        }

        return new RedirectResponse($this->urls->generate('admin.cms.pages.builder', [
            'id' => $page->getId(),
            EditLanguage::PARAMETER => $lang->getId(),
        ]));
    }

    private function buildForm(CmsPage $page, string $locale): FormInterface
    {
        $content = CmsPageContentQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($locale)
            ->findOne();

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
