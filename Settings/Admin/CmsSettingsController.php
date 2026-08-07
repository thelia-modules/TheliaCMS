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

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use TheliaCMS\Menu\Admin\MenuTargetChoices;
use TheliaCMS\Page\Admin\CmsActivityLog;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\EditorProfileSeeder;
use TheliaCMS\Settings\SiteMode;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * The settings of the site: what it is, what it answers when an address does not
 * exist, and whether it is open.
 */
#[Route('/admin/cms/settings', name: 'admin.cms.settings.')]
final readonly class CmsSettingsController
{
    private const string TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/settings/edit.html.twig';

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private CmsSettings $settings,
        private MenuTargetChoices $choices,
        private EditorProfileSeeder $editorProfile,
        private CmsActivityLog $activityLog,
        private EditLanguage $languages,
    ) {
    }

    #[Route('', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $lang = $this->languages->resolve($request);

        $form = $this->forms->create(CmsSettingsType::class, [
            'siteMode' => $this->settings->siteMode()->value,
            'notFoundPageId' => $this->settings->notFoundPageId(),
            'maintenanceActive' => $this->settings->isMaintenanceActive(),
            'maintenancePageId' => $this->settings->maintenancePageId(),
            'maintenanceAllowlist' => implode("\n", $this->settings->maintenanceAllowlist()),
            'trashRetentionDays' => $this->settings->trashRetentionDays(),
        ], [
            'page_choices' => $this->choices->pages($lang->getLocale()),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyUnlessUpdate();

            return $this->save($request, $form->getData());
        }

        return new Response($this->twig->render(self::TEMPLATE, [
            'form' => $form->createView(),
            'edit_locale' => $lang->getLocale(),
            'edit_language_id' => $lang->getId(),
            'is_showcase' => $this->settings->isShowcase(),
            'editor_profile_exists' => $this->editorProfile->exists(),
            'client_ip' => $request->getClientIp(),
            'retry_after' => CmsSettings::RETRY_AFTER,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function save(Request $request, array $data): RedirectResponse
    {
        $mode = SiteMode::fromStorage($data['siteMode']);

        $this->settings->save(
            mode: $mode,
            notFoundPageId: null === $data['notFoundPageId'] ? null : (int) $data['notFoundPageId'],
            maintenanceActive: (bool) $data['maintenanceActive'],
            maintenanceAllowlist: (string) $data['maintenanceAllowlist'],
            maintenancePageId: null === $data['maintenancePageId'] ? null : (int) $data['maintenancePageId'],
            // Left empty means "the default"; a typed 0 means "keep them".
            trashRetentionDays: null === $data['trashRetentionDays'] ? null : (int) $data['trashRetentionDays'],
        );

        // A showcase site is handed to someone who is not an administrator of
        // the shop, so the profile they work under comes with the mode.
        if ($mode->isShowcase() && $this->editorProfile->seed()) {
            $this->flash($request, 'info', 'The "Editor" profile has been created. Assign it to the people who write the site under Configuration > Administrators.');
        }

        $this->activityLog->record('UPDATE', 0, \sprintf('CMS settings saved (mode %s, maintenance %s)', $mode->value, $data['maintenanceActive'] ? 'on' : 'off'), CmsResources::SETTINGS);
        $this->flash($request, 'success', 'Settings saved.');

        return new RedirectResponse($this->urls->generate('admin.cms.settings.edit'));
    }

    private function flash(Request $request, string $type, string $message): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        if (method_exists($session, 'getFlashBag')) {
            $session->getFlashBag()->add($type, $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME));
        }
    }

    private function denyUnlessUpdate(): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::SETTINGS], [], [AccessManager::UPDATE])) {
            throw new AccessDeniedHttpException($this->translator->trans('You are not allowed to change these settings.', [], TheliaCMS::DOMAIN_NAME));
        }
    }
}
