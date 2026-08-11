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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use TheliaCMS\Http\CachePurger;
use TheliaCMS\Http\CacheTags;
use TheliaCMS\Page\Admin\CmsActivityLog;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\Settings\FontStacks;
use TheliaCMS\Settings\SiteFonts;
use TheliaCMS\Settings\SiteStyles;
use TheliaCMS\Settings\SiteTypography;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * The global styles of the site: what the headings, the paragraphs, the links
 * and the buttons of every CMS page look like, the colours the pickers offer,
 * and the fonts the site writes with.
 */
#[Route('/admin/cms/settings/styles', name: 'admin.cms.settings.styles.')]
final readonly class SiteStylesController
{
    private const string TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/settings/styles.html.twig';

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private SiteStyles $styles,
        private SiteFonts $fonts,
        private FontStacks $stacks,
        private CmsActivityLog $activityLog,
        private CachePurger $httpCache,
    ) {
    }

    #[Route('', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        $typography = $this->styles->typography();

        $data = $typography->toArray();
        $data['palette'] = implode("\n", $this->styles->palette());

        $form = $this->forms->create(SiteStylesType::class, $data, [
            'fonts' => $this->fonts->all(),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyUnlessUpdate();

            return $this->save($request, $form->getData(), $form->has('fontDeletions') ? (array) $form->get('fontDeletions')->getData() : [], (array) $form->get('fontUploads')->getData());
        }

        return new Response($this->twig->render(self::TEMPLATE, [
            'form' => $form->createView(),
            'fonts' => $this->fonts->all(),
            'elements' => SiteTypography::ELEMENTS,
        ]));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $deletions
     * @param list<UploadedFile>   $uploads
     */
    private function save(Request $request, array $data, array $deletions, array $uploads): RedirectResponse
    {
        foreach ($uploads as $upload) {
            try {
                $this->fonts->store($upload);
            } catch (\RuntimeException) {
                $this->flash($request, 'danger', 'This file is not a WOFF2 font.');
            }
        }

        foreach ($deletions as $file) {
            $this->fonts->delete($file);
        }

        // The value objects keep what is well-formed and drop the rest; a
        // hand-written value that does not come back after saving was not a
        // valid size, colour or spacing.
        $this->styles->saveTypography(SiteTypography::fromArray($data, $this->stacks));

        $palette = preg_split('/[\s,;]+/', (string) ($data['palette'] ?? '')) ?: [];
        $this->styles->savePalette(array_values(array_filter(array_map(trim(...), $palette))));

        // The stylesheet is linked on every page, under a versioned address:
        // cached pages still point at the previous version.
        $this->httpCache->purge([CacheTags::SITE]);

        $this->activityLog->record('UPDATE', 0, 'Site styles saved', CmsResources::SETTINGS);
        $this->flash($request, 'success', 'Settings saved.');

        return new RedirectResponse($this->urls->generate('admin.cms.settings.styles.edit'));
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
