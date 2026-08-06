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
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * Adds the "Site" section to the back-office sidebar.
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
        $maySeeMedia = $this->securityContext->isGranted(['ADMIN'], [CmsResources::MEDIA], [], [AccessManager::VIEW]);

        // Rendered through the Twig environment rather than BaseHook::render():
        // the parser only knows the module template directories registered for
        // the *active* template, so a namespaced name is the reliable form.
        $event->add($this->twig->render('@TheliaCMSModule/backOffice/default-twig/side-nav.html.twig', [
            'pages_url' => $this->urls->generate('admin.cms.pages.list'),
            'media_url' => $maySeeMedia ? $this->urls->generate('admin.cms.media.list') : null,
            'is_active' => str_starts_with((string) $this->getRequest()?->getPathInfo(), '/admin/cms'),
            'section_label' => $this->trans('Site', [], TheliaCMS::DOMAIN_NAME),
            'pages_label' => $this->trans('Pages', [], TheliaCMS::DOMAIN_NAME),
            'media_label' => $this->trans('Media', [], TheliaCMS::DOMAIN_NAME),
        ]));
    }
}
