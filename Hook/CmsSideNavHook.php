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
use Thelia\Core\Security\Resource\AdminResources;
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
    /** Where every screen of this module answers. */
    private const string SECTION_PATH = '/admin/cms';

    /**
     * The folder and content screens of the shop, linked from this section
     * since it replaces the folder section of the theme.
     *
     * @var list<string>
     */
    private const array FOLDER_PATHS = ['/admin/folder', '/admin/content'];

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
        $maySeePages = $this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [AccessManager::VIEW]);

        // The folders used to have a section of their own: a profile allowed on
        // them and on nothing of the CMS still finds them here.
        if (!$maySeePages && !$this->securityContext->isGranted(['ADMIN'], [AdminResources::FOLDER], [], [AccessManager::VIEW])) {
            return;
        }

        // Each entry is offered only to a profile allowed to open it: a link
        // leading straight to a 403 is worse than no link.
        $maySeeMenus = $this->securityContext->isGranted(['ADMIN'], [CmsResources::MENU], [], [AccessManager::VIEW]);
        $maySeeForms = $this->securityContext->isGranted(['ADMIN'], [CmsResources::FORM], [], [AccessManager::VIEW]);
        $maySeeMedia = $this->securityContext->isGranted(['ADMIN'], [CmsResources::MEDIA], [], [AccessManager::VIEW]);
        $maySeeSettings = $this->securityContext->isGranted(['ADMIN'], [CmsResources::SETTINGS], [], [AccessManager::VIEW]);

        // Rendered through the Twig environment rather than BaseHook::render():
        // the parser only knows the module template directories registered for
        // the *active* template, so a namespaced name is the reliable form.
        // The scripts and site styles screens are not offered: they are closed
        // by CmsAdminGuard, which says why.
        $event->add($this->twig->render('@TheliaCMSModule/backOffice/default-twig/side-nav.html.twig', [
            'pages_url' => $maySeePages ? $this->urls->generate('admin.cms.pages.list') : null,
            // Reusable blocks belong to the page resource: whoever may edit a
            // page may edit what pages share.
            'blocks_url' => $maySeePages ? $this->urls->generate('admin.cms.blocks.list') : null,
            'templates_url' => $maySeePages ? $this->urls->generate('admin.cms.templates.list') : null,
            'menus_url' => $maySeeMenus ? $this->urls->generate('admin.cms.menus.list') : null,
            'forms_url' => $maySeeForms ? $this->urls->generate('admin.cms.forms.list') : null,
            'media_url' => $maySeeMedia ? $this->urls->generate('admin.cms.media.list') : null,
            'settings_url' => $maySeeSettings ? $this->urls->generate('admin.cms.settings.edit') : null,
            'is_active' => $this->isOnACmsScreen(),
            // On a showcase site the content *is* the site, so its section comes
            // before the shop ones. The sidebar is a flex column, so ordering it
            // is a matter of one property rather than of a theme override.
            'is_first' => $this->settings->isShowcase(),
            'section_label' => $this->trans('CMS', [], TheliaCMS::DOMAIN_NAME),
            'pages_label' => $this->trans('Pages', [], TheliaCMS::DOMAIN_NAME),
            'blocks_label' => $this->trans('Blocks', [], TheliaCMS::DOMAIN_NAME),
            'templates_label' => $this->trans('Templates', [], TheliaCMS::DOMAIN_NAME),
            'menus_label' => $this->trans('Menus', [], TheliaCMS::DOMAIN_NAME),
            'folders_label' => $this->trans('Folders', [], TheliaCMS::DOMAIN_NAME),
            'forms_label' => $this->trans('Forms', [], TheliaCMS::DOMAIN_NAME),
            'media_label' => $this->trans('Media', [], TheliaCMS::DOMAIN_NAME),
            'settings_label' => $this->trans('Settings', [], TheliaCMS::DOMAIN_NAME),
        ]));
    }

    /**
     * Whether the screen on display is one of this module.
     *
     * The path is read up to the segment boundary: another module answering on
     * `/admin/cms-import` is not a screen of this one, and would otherwise light
     * up its section of the sidebar.
     */
    private function isOnACmsScreen(): bool
    {
        $path = (string) $this->getRequest()?->getPathInfo();

        if (self::SECTION_PATH === $path || str_starts_with($path, self::SECTION_PATH.'/')) {
            return true;
        }

        // Matched the way the theme matches its own folder section, as a prefix:
        // the folder screens answer on `/admin/folders` and `/admin/folder/...`.
        foreach (self::FOLDER_PATHS as $folderPath) {
            if (str_starts_with($path, $folderPath)) {
                return true;
            }
        }

        return false;
    }
}
