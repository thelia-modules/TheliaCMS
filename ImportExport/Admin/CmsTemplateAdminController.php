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

namespace TheliaCMS\ImportExport\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
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
use TheliaCMS\Model\CmsPageTemplate;
use TheliaCMS\Model\CmsPageTemplateQuery;
use TheliaCMS\Page\Admin\CmsPageAdminRepository;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * Templates: the pages an editor starts from.
 *
 * A template is a page kept aside. Starting from one always produces a hidden
 * draft, so a template can be tried without anything appearing on the site.
 */
#[Route('/admin/cms/templates', name: 'admin.cms.templates.')]
final readonly class CmsTemplateAdminController
{
    private const string LIST_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/templates/list.html.twig';

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private EditLanguage $languages,
        private CmsPageAdminRepository $pages,
        private PageTemplateWriter $writer,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET', 'POST'])]
    public function list(Request $request): Response
    {
        $this->denyUnless(AccessManager::VIEW);

        $lang = $this->languages->resolve($request);
        $locale = $lang->getLocale();
        $pages = $this->pages->parentChoices($locale);

        $saveForm = $this->forms->create(SaveAsTemplateType::class, null, [
            'pages' => $pages,
            'action' => $this->urls->generate('admin.cms.templates.list', [EditLanguage::PARAMETER => $lang->getId()]),
        ]);

        $saveForm->handleRequest($request);

        if ($saveForm->isSubmitted() && $saveForm->isValid()) {
            return $this->saveTemplate($request, $saveForm, $lang->getId());
        }

        $templates = [];

        foreach (CmsPageTemplateQuery::create()->orderByTitle()->find() as $template) {
            $templates[] = [
                'template' => $template,
                'locales' => $this->localesOf($template),
                'form' => $this->newPageForm($template, $locale, $lang->getId())->createView(),
            ];
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'templates' => $templates,
            'save_form' => $saveForm->createView(),
            'has_pages' => [] !== $pages,
            'may_write' => $this->isGranted(AccessManager::CREATE),
            'edit_language_id' => $lang->getId(),
            'edit_locale' => $locale,
        ]));
    }

    #[Route('/{id}/use', name: 'use', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function use(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::CREATE);

        $template = $this->templateOrFail($id);
        $lang = $this->languages->resolve($request);
        $form = $this->newPageForm($template, $lang->getLocale(), $lang->getId());

        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->flash($request, 'danger', 'The new page needs a title.');

            return $this->backToList($lang->getId());
        }

        $data = $form->getData();

        try {
            $page = $this->writer->createPage(
                $template,
                (string) $data['title'],
                (int) ($data['parentId'] ?? 0),
                $lang->getLocale(),
            );
        } catch (\InvalidArgumentException $exception) {
            $this->flash($request, 'danger', $exception->getMessage());

            return $this->backToList($lang->getId());
        }

        $this->flash($request, 'success', 'The page has been created as a draft. Nothing is online until you publish it.');

        // Straight into the editor: starting from a template is the first half
        // of writing a page, not the end of a task.
        return new RedirectResponse($this->urls->generate('admin.cms.pages.builder', [
            'id' => $page->getId(),
            EditLanguage::PARAMETER => $lang->getId(),
        ]));
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::DELETE);

        $this->writer->delete($this->templateOrFail($id));
        $this->flash($request, 'success', 'The template has been removed. The pages made from it are untouched.');

        return $this->backToList($this->languages->resolve($request)->getId());
    }

    private function saveTemplate(Request $request, FormInterface $form, int $langId): Response
    {
        $this->denyUnless(AccessManager::CREATE);

        $data = $form->getData();
        $page = $this->pages->findLive((int) $data['pageId']);

        if (null === $page) {
            $this->flash($request, 'danger', 'That page is no longer here.');

            return $this->backToList($langId);
        }

        $this->writer->saveFromPage($page, (string) $data['title'], $data['description'] ?? null);
        $this->flash($request, 'success', 'The template has been saved.');

        return $this->backToList($langId);
    }

    private function newPageForm(CmsPageTemplate $template, string $locale, int $langId): FormInterface
    {
        // One form per row on the same page, each under a name of its own:
        // sharing a name would give every row the same field ids, and a label
        // would then focus the first row whichever one was clicked.
        return $this->forms->createNamed(
            'template_'.$template->getId(),
            PageFromTemplateType::class,
            ['title' => $template->getTitle()],
            [
                'parents' => $this->pages->parentChoices($locale),
                'action' => $this->urls->generate('admin.cms.templates.use', [
                    'id' => $template->getId(),
                    EditLanguage::PARAMETER => $langId,
                ]),
            ],
        );
    }

    /**
     * The languages a template carries content in, so an editor knows what they
     * are about to get.
     *
     * @return list<string>
     */
    private function localesOf(CmsPageTemplate $template): array
    {
        try {
            $payload = json_decode((string) $template->getPayload(), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $locales = array_keys($payload['pages'][0]['translations'] ?? []);

        return array_values(array_map(strval(...), $locales));
    }

    private function templateOrFail(int $id): CmsPageTemplate
    {
        $template = CmsPageTemplateQuery::create()
            ->filterById($id, Criteria::EQUAL)
            ->findOne();

        if (!$template instanceof CmsPageTemplate) {
            throw new NotFoundHttpException();
        }

        return $template;
    }

    private function backToList(int $langId): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate('admin.cms.templates.list', [EditLanguage::PARAMETER => $langId]));
    }

    private function isGranted(string $access): bool
    {
        return $this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [$access]);
    }

    private function denyUnless(string $access): void
    {
        if (!$this->isGranted($access)) {
            throw new AccessDeniedHttpException($this->translate('You are not allowed to manage the pages of this site.'));
        }
    }

    private function flash(Request $request, string $type, string $message): void
    {
        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add($type, $this->translate($message));
        }
    }

    private function translate(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
