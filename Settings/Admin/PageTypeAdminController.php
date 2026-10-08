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

namespace TheliaCMS\Settings\Admin;

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
use TheliaCMS\Page\PageTemplateResolver;
use TheliaCMS\Page\PageTypeCode;
use TheliaCMS\Page\PageTypeInUseException;
use TheliaCMS\Page\PageTypeRepository;
use TheliaCMS\Page\PageTypeWriter;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * The page types of the site, and the template each one is displayed with.
 *
 * Under /admin/cms/settings, so CmsAdminGuard holds it to the settings
 * permission like the rest of the section.
 */
#[Route('/admin/cms/settings/page-types', name: 'admin.cms.settings.page_types.')]
final readonly class PageTypeAdminController
{
    private const string TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/settings/page-types.html.twig';

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private PageTypeRepository $pageTypes,
        private PageTypeWriter $writer,
        private PageTemplateResolver $templates,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET', 'POST'])]
    public function list(Request $request): Response
    {
        $form = $this->forms->create(PageTypeCreateType::class, null, [
            'action' => $this->urls->generate('admin.cms.settings.page_types.list'),
        ]);

        $form->handleRequest($request);

        // The right is checked on any submission, before the form is read: a
        // profile that may only look gets a refusal, not a validation error.
        if ($form->isSubmitted()) {
            $this->denyUnless(AccessManager::CREATE);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $added = $this->add($request, $form);

            if (null !== $added) {
                return $added;
            }
        }

        $rows = [];
        $pageCounts = $this->pageTypes->pageCountsByType();

        foreach ($this->pageTypes->codes() as $code) {
            $rows[] = [
                'code' => $code,
                'template' => $this->templates->resolve($code),
                'pages' => $pageCounts[$code] ?? 0,
                'deletable' => PageTypeCode::isDeletable($code),
            ];
        }

        return new Response($this->twig->render(self::TEMPLATE, [
            'types' => $rows,
            'form' => $form->createView(),
            'may_create' => $this->isGranted(AccessManager::CREATE),
            'may_delete' => $this->isGranted(AccessManager::DELETE),
        ]));
    }

    #[Route('/{code}/delete', name: 'delete', requirements: ['code' => PageTypeCode::ROUTE_REQUIREMENT], methods: ['POST'])]
    public function delete(Request $request, string $code): Response
    {
        $this->denyUnless(AccessManager::DELETE);

        if (!PageTypeCode::isDeletable($code) || !$this->pageTypes->exists($code)) {
            throw new NotFoundHttpException();
        }

        try {
            $this->writer->delete($code);
        } catch (PageTypeInUseException $exception) {
            $this->flash($request, 'danger', $this->translator->trans(
                'The type "%code%" is still used by %count% page(s), the bin included. Give them another type first.',
                ['%code%' => $code, '%count%' => $exception->pages],
                TheliaCMS::DOMAIN_NAME,
            ));

            return $this->backToList();
        }

        $this->flash($request, 'success', $this->translator->trans('The page type has been deleted.', [], TheliaCMS::DOMAIN_NAME));

        return $this->backToList();
    }

    private function add(Request $request, FormInterface $form): ?RedirectResponse
    {
        $code = (string) $form->get('code')->getData();

        if ($this->pageTypes->exists($code)) {
            $form->get('code')->addError(new FormError($this->translator->trans('This type exists already.', [], TheliaCMS::DOMAIN_NAME)));

            return null;
        }

        $this->writer->add($code);
        $this->flash($request, 'success', $this->translator->trans('The page type has been added.', [], TheliaCMS::DOMAIN_NAME));

        return $this->backToList();
    }

    private function backToList(): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate('admin.cms.settings.page_types.list'));
    }

    private function flash(Request $request, string $type, string $message): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if (method_exists($session, 'getFlashBag')) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    private function isGranted(string $access): bool
    {
        return $this->securityContext->isGranted(['ADMIN'], [CmsResources::SETTINGS], [], [$access]);
    }

    private function denyUnless(string $access): void
    {
        if (!$this->isGranted($access)) {
            throw new AccessDeniedHttpException($this->translator->trans('You are not allowed to change these settings.', [], TheliaCMS::DOMAIN_NAME));
        }
    }
}
