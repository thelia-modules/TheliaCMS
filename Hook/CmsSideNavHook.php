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

namespace TheliaCMS\Hook;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Core\Template\Parser\ParserResolver;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Settings\CmsSettings;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * Adds the "CMS" section to the back-office sidebar.
 *
 * Dependencies are injected through the constructor: with #[Required] setters
 * the hook renders empty without ever reporting an error.
 */
class CmsSideNavHook extends BaseHook
{
    public function __construct(
        private readonly SecurityContext $securityContext,
        private readonly UrlGeneratorInterface $urls,
        private readonly Environment $twig,
        private readonly CmsSettings $settings,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'main.in-top-menu-items' => [
                ['type' => 'back', 'method' => 'onMainInTopMenuItems'],
            ],
        ];
    }

    public function onMainInTopMenuItems(HookRenderEvent $event): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [AccessManager::VIEW])) {
            return;
        }

        // Each entry is offered only to a profile allowed to open it: a link
        // leading straight to a 403 is worse than no link.
        $maySeeMenus = $this->securityContext->isGranted(['ADMIN'], [CmsResources::MENU], [], [AccessManager::VIEW]);
        $maySeeForms = $this->securityContext->isGranted(['ADMIN'], [CmsResources::FORM], [], [AccessManager::VIEW]);
        $maySeeMedia = $this->securityContext->isGranted(['ADMIN'], [CmsResources::MEDIA], [], [AccessManager::VIEW]);
        $maySeeSettings = $this->securityContext->isGranted(['ADMIN'], [CmsResources::SETTINGS], [], [AccessManager::VIEW]);
        $maySeeScripts = $this->securityContext->isGranted(['ADMIN'], [CmsResources::CUSTOM_CODE], [], [AccessManager::VIEW]);

        // Rendered through the Twig environment rather than BaseHook::render():
        // the parser only knows the module template directories registered for
        // the *active* template, so a namespaced name is the reliable form.
        $event->add($this->twig->render('@TheliaCMSModule/backOffice/default-twig/side-nav.html.twig', [
            'pages_url' => $this->urls->generate('admin.cms.pages.list'),
            // Reusable blocks belong to the page resource: whoever may edit a
            // page may edit what pages share.
            'blocks_url' => $this->urls->generate('admin.cms.blocks.list'),
            'templates_url' => $this->urls->generate('admin.cms.templates.list'),
            'menus_url' => $maySeeMenus ? $this->urls->generate('admin.cms.menus.list') : null,
            'forms_url' => $maySeeForms ? $this->urls->generate('admin.cms.forms.list') : null,
            'media_url' => $maySeeMedia ? $this->urls->generate('admin.cms.media.list') : null,
            'settings_url' => $maySeeSettings ? $this->urls->generate('admin.cms.settings.edit') : null,
            'scripts_url' => $maySeeScripts ? $this->urls->generate('admin.cms.scripts.list') : null,
            'is_active' => str_starts_with((string) $this->getRequest()?->getPathInfo(), '/admin/cms'),
            // On a showcase site the content *is* the site, so its section comes
            // before the shop ones. The sidebar is a flex column, so ordering it
            // is a matter of one property rather than of a theme override.
            'is_first' => $this->settings->isShowcase(),
            'section_label' => $this->trans('CMS', [], TheliaCMS::DOMAIN_NAME),
            'pages_label' => $this->trans('Pages', [], TheliaCMS::DOMAIN_NAME),
            'blocks_label' => $this->trans('Blocks', [], TheliaCMS::DOMAIN_NAME),
            'templates_label' => $this->trans('Templates', [], TheliaCMS::DOMAIN_NAME),
            'menus_label' => $this->trans('Menus', [], TheliaCMS::DOMAIN_NAME),
            'forms_label' => $this->trans('Forms', [], TheliaCMS::DOMAIN_NAME),
            'media_label' => $this->trans('Media', [], TheliaCMS::DOMAIN_NAME),
            'settings_label' => $this->trans('Settings', [], TheliaCMS::DOMAIN_NAME),
            'scripts_label' => $this->trans('Scripts and measurement', [], TheliaCMS::DOMAIN_NAME),
        ]));
    }
}
