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

namespace TheliaCMS\Page\Admin;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\HttpFoundation\Session\Session as TheliaSession;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * Page tree back office. The controller only resolves input, delegates the
 * write to CmsPageWriter and redirects; nothing is persisted here.
 */
#[Route('/admin/cms/pages', name: 'admin.cms.pages.')]
final readonly class CmsPageAdminController
{
    private const string LIST_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/pages/list.html.twig';
    private const string EDIT_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/pages/edit.html.twig';
    private const string TRASH_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/pages/trash.html.twig';

    /** Same query parameter as the back-office language switcher component. */
    private const string EDIT_LANGUAGE_PARAMETER = 'edit_language_id';

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private CmsPageAdminRepository $pages,
        private CmsPageWriter $writer,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $lang = $this->editLang($request);
        $locale = $lang->getLocale();

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'rows' => $this->pages->tree($locale),
            'edit_locale' => $locale,
            'edit_language_id' => $lang->getId(),
            'home_page_id' => (int) TheliaCMS::getConfigValue('home_page_id', 0),
            'trash_count' => \count($this->pages->trash($locale)),
        ]));
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $this->denyUnless(AccessManager::CREATE);

        return $this->handleForm($request, new CmsPage());
    }

    #[Route('/{id}', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        return $this->handleForm($request, $this->livePageOrFail($id));
    }

    #[Route('/{id}/publish', name: 'publish', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function publish(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::UPDATE);
        $lang = $this->editLang($request);

        $this->writer->publish($this->livePageOrFail($id), $lang->getLocale());

        return $this->backToEdit($id, $lang);
    }

    #[Route('/{id}/unpublish', name: 'unpublish', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unpublish(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::UPDATE);
        $lang = $this->editLang($request);

        $this->writer->unpublish($this->livePageOrFail($id), $lang->getLocale());

        return $this->backToEdit($id, $lang);
    }

    #[Route('/{id}/visibility', name: 'visibility', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleVisibility(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::UPDATE);

        $this->writer->toggleVisibility($this->livePageOrFail($id));

        return $this->backToList($request);
    }

    #[Route('/{id}/move/{direction}', name: 'move', requirements: ['id' => '\d+', 'direction' => 'up|down'], methods: ['POST'])]
    public function move(Request $request, int $id, string $direction): Response
    {
        $this->denyUnless(AccessManager::UPDATE);

        $this->writer->move($this->livePageOrFail($id), 'up' === $direction ? -1 : 1);

        return $this->backToList($request);
    }

    #[Route('/{id}/duplicate', name: 'duplicate', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function duplicate(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::CREATE);
        $lang = $this->editLang($request);

        $copy = $this->writer->duplicate(
            $this->livePageOrFail($id),
            $lang->getLocale(),
            $this->translator->trans('(copy)', [], TheliaCMS::DOMAIN_NAME),
        );

        return $this->backToEdit((int) $copy->getId(), $lang);
    }

    #[Route('/{id}/set-as-home', name: 'set_as_home', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function setAsHome(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::UPDATE);

        $this->writer->setAsHome($this->livePageOrFail($id));

        return $this->backToList($request);
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::DELETE);

        $this->writer->moveToTrash($this->livePageOrFail($id));

        return $this->backToList($request);
    }

    #[Route('/trash', name: 'trash', methods: ['GET'], priority: 1)]
    public function trash(Request $request): Response
    {
        $lang = $this->editLang($request);

        return new Response($this->twig->render(self::TRASH_TEMPLATE, [
            'pages' => $this->pages->trash($lang->getLocale()),
            'edit_locale' => $lang->getLocale(),
            'edit_language_id' => $lang->getId(),
        ]));
    }

    #[Route('/{id}/restore', name: 'restore', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function restore(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::UPDATE);
        $lang = $this->editLang($request);

        $page = $this->pages->findDeleted($id);

        if (!$page instanceof CmsPage) {
            throw new NotFoundHttpException();
        }

        try {
            $this->writer->restore($page, $lang->getLocale());
        } catch (\DomainException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        return $this->backToList($request);
    }

    private function handleForm(Request $request, CmsPage $page): Response
    {
        $lang = $this->editLang($request);
        $locale = $lang->getLocale();
        $form = $this->buildForm($page, $locale);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyUnless($page->isNew() ? AccessManager::CREATE : AccessManager::UPDATE);
            $this->applyTo($page, $locale, $form);

            return $this->backToEdit((int) $page->getId(), $lang);
        }

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'form' => $form->createView(),
            'page' => $page,
            'status' => $page->isNew() ? PageStatus::Draft : $this->pages->statusOf($page, $locale),
            'edit_locale' => $locale,
            'edit_language_id' => $lang->getId(),
            'is_home' => !$page->isNew() && (int) $page->getId() === (int) TheliaCMS::getConfigValue('home_page_id', 0),
        ]));
    }

    private function buildForm(CmsPage $page, string $locale): FormInterface
    {
        $content = $page->isNew() ? null : CmsPageContentQuery::create()
            ->filterByPageId($page->getId())
            ->filterByLocale($locale)
            ->findOne();

        if (!$page->isNew()) {
            $page->setLocale($locale);
        }

        return $this->forms->create(CmsPageType::class, [
            'title' => $page->isNew() ? '' : (string) $page->getTitle(),
            'slug' => $page->isNew() ? null : $page->getRewrittenUrl($locale),
            'parent' => (int) $page->getParent(),
            'layout' => $page->getLayout() ?? 'default',
            'visible' => $page->isNew() ? 1 : $page->getVisible(),
            'publishAt' => $page->getPublishAt(),
            'unpublishAt' => $page->getUnpublishAt(),
            'html' => $content?->getDraftHtml(),
            'metaTitle' => $page->isNew() ? null : $page->getMetaTitle(),
            'metaDescription' => $page->isNew() ? null : $page->getMetaDescription(),
            'ogTitle' => $page->isNew() ? null : $page->getOgTitle(),
            'ogDescription' => $page->isNew() ? null : $page->getOgDescription(),
            'twitterCard' => $page->isNew() ? null : $page->getTwitterCard(),
            'canonical' => $page->isNew() ? null : $page->getCanonical(),
            'noindex' => $page->isNew() ? 0 : $page->getNoindex(),
            'nofollow' => $page->isNew() ? 0 : $page->getNofollow(),
        ], [
            'parent_choices' => $this->pages->parentChoices($locale, $page->isNew() ? null : (int) $page->getId()),
        ]);
    }

    private function applyTo(CmsPage $page, string $locale, FormInterface $form): void
    {
        $data = $form->getData();

        $page->setParent((int) $data['parent'])
            ->setLayout($data['layout'])
            ->setVisible((int) $data['visible'])
            ->setPublishAt($data['publishAt'])
            ->setUnpublishAt($data['unpublishAt']);

        // The i18n columns are written on the localized object before the
        // writer saves it, so a single save() covers page + translation.
        $page->setLocale($locale)
            ->setMetaTitle($data['metaTitle'])
            ->setMetaDescription($data['metaDescription'])
            ->setOgTitle($data['ogTitle'])
            ->setOgDescription($data['ogDescription'])
            ->setTwitterCard($data['twitterCard'])
            ->setCanonical($data['canonical'])
            ->setNoindex((int) $data['noindex'])
            ->setNofollow((int) $data['nofollow']);

        $this->writer->saveDraft($page, $locale, new PageDraft(
            title: $data['title'],
            slug: $data['slug'],
            html: $data['html'],
        ));
    }

    private function livePageOrFail(int $id): CmsPage
    {
        $page = $this->pages->findLive($id);

        if (!$page instanceof CmsPage) {
            throw new NotFoundHttpException();
        }

        return $page;
    }

    private function denyUnless(string $access): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [$access])) {
            throw new AccessDeniedHttpException($this->translator->trans('You are not allowed to change CMS pages.', [], TheliaCMS::DOMAIN_NAME));
        }
    }

    /**
     * Language whose translation of the page is being edited — independent from
     * the language the back office itself is displayed in.
     *
     * `edit_language_id` is the parameter the back-office language switcher
     * already writes, so the CMS screens follow the same convention as the
     * product and folder screens.
     */
    private function editLang(Request $request): Lang
    {
        $active = $this->activeLangs();
        $session = $request->hasSession() ? $request->getSession() : null;
        $requested = $request->query->getInt(self::EDIT_LANGUAGE_PARAMETER);

        foreach ($active as $lang) {
            if ($lang->getId() === $requested) {
                // Shared with the rest of the back office, so switching the
                // edition language here carries over to the product and folder
                // screens, and vice versa.
                if ($session instanceof TheliaSession) {
                    $session->setAdminEditionLang($lang);
                }

                return $lang;
            }
        }

        $current = $session instanceof TheliaSession ? $session->getAdminEditionLang() : Lang::getDefaultLanguage();

        foreach ($active as $lang) {
            if ($lang->getId() === $current->getId()) {
                return $lang;
            }
        }

        // The stored language has since been switched off.
        return $active[0] ?? Lang::getDefaultLanguage();
    }

    /**
     * Only languages the shop has switched on: offering a translation tab for a
     * disabled language invites work that is never published.
     *
     * @return list<Lang>
     */
    private function activeLangs(): array
    {
        return array_values(iterator_to_array(
            LangQuery::create()->filterByActive(1)->orderByPosition()->find(),
            false
        ));
    }

    private function backToList(Request $request): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate('admin.cms.pages.list', [
            self::EDIT_LANGUAGE_PARAMETER => $this->editLang($request)->getId(),
        ]));
    }

    private function backToEdit(int $id, Lang $lang): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate('admin.cms.pages.edit', [
            'id' => $id,
            self::EDIT_LANGUAGE_PARAMETER => $lang->getId(),
        ]));
    }
}
