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

namespace TheliaCMS\Script\Admin;

use Symfony\Component\Form\FormFactoryInterface;
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
use TheliaCMS\Consent\ConsentSettings;
use TheliaCMS\Model\CmsScript;
use TheliaCMS\Model\CmsScriptQuery;
use TheliaCMS\Page\Admin\CmsActivityLog;
use TheliaCMS\Script\ScriptPlacement;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * Third-party snippets and the consent platform that gates them.
 *
 * Behind the custom-code permission rather than the settings one: whoever can
 * paste a script tag onto every page of the site can do anything a visitor's
 * browser can do, which is not a permission an editor of pages needs.
 */
#[Route('/admin/cms/scripts', name: 'admin.cms.scripts.')]
final readonly class CmsScriptAdminController
{
    private const string LIST_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/scripts/list.html.twig';
    private const string EDIT_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/scripts/edit.html.twig';

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private ConsentSettings $consent,
        private CmsActivityLog $activityLog,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET', 'POST'])]
    public function list(Request $request): Response
    {
        $form = $this->forms->create(ConsentSettingsType::class, [
            'clientId' => $this->consent->clientId(),
            'cookiesVersion' => $this->consent->cookiesVersion(),
            'consentMap' => json_encode($this->consent->consentMap(), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
        ], ['action' => $this->urls->generate('admin.cms.scripts.list')]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyUnless(AccessManager::UPDATE);

            $data = $form->getData();
            $this->consent->save($data['clientId'], $data['cookiesVersion'], $data['consentMap']);
            $this->activityLog->record('UPDATE', 0, 'CMS consent settings saved', CmsResources::CUSTOM_CODE);
            $this->flash($request, 'success', 'Settings saved.');

            return new RedirectResponse($this->urls->generate('admin.cms.scripts.list'));
        }

        $labels = [];
        $help = [];

        foreach (ScriptPlacement::cases() as $placement) {
            $labels[$placement->value] = $placement->label();
            $help[$placement->value] = $placement->help();
        }

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'scripts' => $this->byPlacement(),
            'placement_labels' => $labels,
            'placement_help' => $help,
            'consent_form' => $form->createView(),
            'consent_configured' => $this->consent->isConfigured(),
            'may_write' => $this->isGranted(AccessManager::UPDATE),
        ]));
    }

    #[Route('/new', name: 'create', methods: ['GET', 'POST'], priority: 1)]
    public function create(Request $request): Response
    {
        $this->denyUnless(AccessManager::CREATE);

        return $this->editScript($request, new CmsScript());
    }

    #[Route('/{id}', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        return $this->editScript($request, $this->scriptOrFail($id));
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $this->denyUnless(AccessManager::DELETE);

        $script = $this->scriptOrFail($id);
        $title = (string) $script->getTitle();
        $script->delete();

        $this->activityLog->record('DELETE', $id, \sprintf('CMS script "%s" deleted', $title), CmsResources::CUSTOM_CODE);
        $this->flash($request, 'success', 'The snippet has been removed.');

        return new RedirectResponse($this->urls->generate('admin.cms.scripts.list'));
    }

    private function editScript(Request $request, CmsScript $script): Response
    {
        $isNew = $script->isNew();

        $form = $this->forms->create(CmsScriptType::class, [
            'title' => $script->getTitle(),
            'placement' => $isNew ? ScriptPlacement::Head->value : $script->getPlacement(),
            'consentCategory' => $script->getConsentCategory(),
            'content' => $script->getContent(),
            'note' => $script->getNote(),
            'active' => 1 === $script->getActive(),
        ], [
            'action' => $isNew
                ? $this->urls->generate('admin.cms.scripts.create')
                : $this->urls->generate('admin.cms.scripts.edit', ['id' => $script->getId()]),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyUnless($isNew ? AccessManager::CREATE : AccessManager::UPDATE);
            $this->save($script, $form->getData());

            $this->activityLog->record(
                $isNew ? 'CREATE' : 'UPDATE',
                (int) $script->getId(),
                \sprintf('CMS script "%s" saved', (string) $script->getTitle()),
                CmsResources::CUSTOM_CODE,
            );
            $this->flash($request, 'success', 'The snippet has been saved.');

            return new RedirectResponse($this->urls->generate('admin.cms.scripts.list'));
        }

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'script' => $isNew ? null : $script,
            'form' => $form->createView(),
            'consent_configured' => $this->consent->isConfigured(),
            'known_vendors' => array_keys($this->consent->consentMap()),
            'may_write' => $this->isGranted($isNew ? AccessManager::CREATE : AccessManager::UPDATE),
        ]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function save(CmsScript $script, array $data): void
    {
        $adminId = $this->securityContext->getAdminUser()?->getId();

        $script
            ->setTitle((string) $data['title'])
            ->setPlacement(ScriptPlacement::fromStorage($data['placement'])->value)
            ->setConsentCategory(trim((string) $data['consentCategory']) ?: null)
            ->setContent((string) $data['content'])
            ->setNote(trim((string) $data['note']) ?: null)
            ->setActive($data['active'] ? 1 : 0)
            ->setUpdatedBy($adminId);

        if ($script->isNew()) {
            $script->setCreatedBy($adminId);
        }

        $script->save();
    }

    /**
     * @return array<string, list<CmsScript>>
     */
    private function byPlacement(): array
    {
        $grouped = [];

        foreach (ScriptPlacement::cases() as $placement) {
            $grouped[$placement->value] = [];
        }

        foreach (CmsScriptQuery::create()->orderById()->find() as $script) {
            $grouped[ScriptPlacement::fromStorage($script->getPlacement())->value][] = $script;
        }

        return $grouped;
    }

    private function scriptOrFail(int $id): CmsScript
    {
        $script = CmsScriptQuery::create()->findPk($id);

        if (!$script instanceof CmsScript) {
            throw new NotFoundHttpException();
        }

        return $script;
    }

    private function isGranted(string $access): bool
    {
        return $this->securityContext->isGranted(['ADMIN'], [CmsResources::CUSTOM_CODE], [], [$access]);
    }

    private function denyUnless(string $access): void
    {
        if (!$this->isGranted($access)) {
            throw new AccessDeniedHttpException($this->translate('You are not allowed to change the scripts of this site.'));
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
