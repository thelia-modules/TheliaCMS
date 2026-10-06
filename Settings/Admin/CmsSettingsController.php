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
use TheliaCMS\Builder\BlockCatalog;
use TheliaCMS\Builder\EditorBaseBlocks;
use TheliaCMS\Http\CachePurger;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\Menu\Admin\MenuTargetChoices;
use TheliaCMS\Page\Admin\CmsActivityLog;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Partial\PartialRegistry;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\Settings\DisabledBlocks;
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
        private CachePurger $httpCache,
        private BlockCatalog $catalog,
        private PartialRegistry $partials,
        private EditorBaseBlocks $baseBlocks,
    ) {
    }

    #[Route('', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $lang = $this->languages->resolve($request);
        $offered = $this->offeredBlocks($lang->getLocale());
        $disabled = $this->settings->disabledBlocks();

        $form = $this->forms->create(CmsSettingsType::class, [
            'siteMode' => $this->settings->siteMode()->value,
            'notFoundPageId' => $this->settings->notFoundPageId(),
            'maintenanceActive' => $this->settings->isMaintenanceActive(),
            'maintenancePageId' => $this->settings->maintenancePageId(),
            'maintenanceAllowlist' => implode("\n", $this->settings->maintenanceAllowlist()),
            'trashRetentionDays' => $this->settings->trashRetentionDays(),
            'httpCacheTtl' => $this->settings->httpCacheTtl(),
            'enabledBlocks' => array_map(static fn (array $group): array => self::enabledAmong($group['choices'], $disabled), $offered),
        ], [
            'page_choices' => $this->choices->pages($lang->getLocale()),
            'block_groups' => $offered,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyUnlessUpdate();

            return $this->save($request, $form->getData(), $offered);
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
     * @param array<string, mixed>                                                    $data
     * @param array<string, array{label: string, choices: array<string, string>}> $offered
     */
    private function save(Request $request, array $data, array $offered): RedirectResponse
    {
        $mode = SiteMode::fromStorage($data['siteMode']);
        $disabledBlocks = $this->settings->disabledBlocks()->afterChoosing(
            offered: array_merge(...array_map(static fn (array $group): array => array_values($group['choices']), array_values($offered))),
            enabled: array_merge(...array_map('array_values', array_values($data['enabledBlocks'] ?? []))),
        );

        $this->settings->save(
            mode: $mode,
            notFoundPageId: null === $data['notFoundPageId'] ? null : (int) $data['notFoundPageId'],
            maintenanceActive: (bool) $data['maintenanceActive'],
            maintenanceAllowlist: (string) $data['maintenanceAllowlist'],
            maintenancePageId: null === $data['maintenancePageId'] ? null : (int) $data['maintenancePageId'],
            // Left empty means "the default"; a typed 0 means "keep them".
            trashRetentionDays: null === $data['trashRetentionDays'] ? null : (int) $data['trashRetentionDays'],
            httpCacheTtl: null === $data['httpCacheTtl'] ? null : (int) $data['httpCacheTtl'],
            disabledBlocks: $disabledBlocks,
        );

        // A showcase site is handed to someone who is not an administrator of
        // the shop, so the profile they work under comes with the mode.
        if ($mode->isShowcase() && $this->editorProfile->seed()) {
            $this->flash($request, 'info', 'The "Editor" profile has been created. Assign it to the people who write the site under Configuration > Administrators.');
        }

        // These settings show on every page, so every cached page is stale.
        $this->httpCache->purge([CacheTags::SITE]);

        $this->activityLog->record('UPDATE', 0, \sprintf(
            'CMS settings saved (mode %s, maintenance %s, blocks off: %s)',
            $mode->value,
            $data['maintenanceActive'] ? 'on' : 'off',
            $disabledBlocks->isEmpty() ? 'none' : implode(', ', $disabledBlocks->ids()),
        ), CmsResources::SETTINGS);
        $this->flash($request, 'success', 'Settings saved.');

        return new RedirectResponse($this->urls->generate('admin.cms.settings.edit'));
    }

    /**
     * Every block the editor can offer, grouped as the panel groups them: the
     * three categories of the editor itself, the catalogue by category, then
     * the dynamic blocks. Each group maps the label an editor reads to the id
     * the setting stores.
     *
     * @return array<string, array{label: string, choices: array<string, string>}>
     */
    private function offeredBlocks(string $locale): array
    {
        $groups = [];

        foreach ($this->baseBlocks->groups() as $key => $group) {
            $groups[$key] = ['label' => $group['label'], 'choices' => self::choices($group['blocks'])];
        }

        $categories = [];

        foreach ($this->catalog->blocks($locale) as $block) {
            $categories[$block->category][$block->id] = $block->label;
        }

        foreach (array_values($categories) as $index => $blocks) {
            $groups['catalog'.$index] = ['label' => array_keys($categories)[$index], 'choices' => self::choices($blocks)];
        }

        $partials = [];

        foreach ($this->partials->all() as $partial) {
            $partials[$partial->name()] = $partial->label();
        }

        $groups['partials'] = ['label' => $this->translator->trans('Live content', [], TheliaCMS::DOMAIN_NAME), 'choices' => self::choices($partials)];

        return $groups;
    }

    /**
     * Choices of a form field, label to id. Two blocks may well share a name
     * in the panel, where their icon and their category tell them apart; here
     * the id does.
     *
     * @param array<string, string> $labelsById
     *
     * @return array<string, string>
     */
    private static function choices(array $labelsById): array
    {
        $choices = [];

        foreach ($labelsById as $id => $label) {
            $choices[isset($choices[$label]) ? \sprintf('%s (%s)', $label, $id) : $label] = $id;
        }

        return $choices;
    }

    /**
     * @param array<string, string> $choices
     *
     * @return list<string>
     */
    private static function enabledAmong(array $choices, DisabledBlocks $disabled): array
    {
        return array_values(array_filter($choices, static fn (string $id): bool => !$disabled->contains($id)));
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
