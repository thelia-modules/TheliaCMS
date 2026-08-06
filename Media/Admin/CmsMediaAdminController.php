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

namespace TheliaCMS\Media\Admin;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use Thelia\Model\Lang;
use TheliaCMS\Media\CmsMediaLibrary;
use TheliaCMS\Page\Admin\EditLanguage;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use TheliaLibrary\Model\LibraryImage;
use Twig\Environment;

/**
 * The media library, as an editor works with it: a grid to look through, and
 * one screen per image to describe it.
 */
#[Route('/admin/cms/media', name: 'admin.cms.media.')]
final readonly class CmsMediaAdminController
{
    private const string LIST_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/media/list.html.twig';
    private const string EDIT_TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/media/edit.html.twig';

    private const string EDIT_LANGUAGE_PARAMETER = EditLanguage::PARAMETER;

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private CmsMediaLibrary $library,
        private MediaCatalog $catalog,
        private MediaUsageFinder $usages,
        private CmsMediaWriter $writer,
        private EditLanguage $languages,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $lang = $this->editLang($request);
        $search = trim($request->query->getString('search'));

        return new Response($this->twig->render(self::LIST_TEMPLATE, [
            'items' => $this->catalog->grid($lang->getLocale(), '' !== $search ? $search : null),
            'search' => $search,
            'has_library' => [] !== $this->library->images(),
            'accepted_mime_types' => implode(',', CmsMediaType::ACCEPTED_MIME_TYPES),
            'edit_locale' => $lang->getLocale(),
            'edit_language_id' => $lang->getId(),
        ]));
    }

    #[Route('', name: 'add', methods: ['POST'])]
    public function add(Request $request): RedirectResponse
    {
        $this->denyUnless(AccessManager::CREATE);
        $lang = $this->editLang($request);

        /** @var list<UploadedFile> $files */
        $files = array_filter(
            $request->files->all()['files'] ?? [],
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        );

        if ([] === $files) {
            return $this->backToList($request, $lang, $this->translator->trans('Choose at least one image to add.', [], TheliaCMS::DOMAIN_NAME), 'warning');
        }

        $stored = $this->writer->add(array_values($files), $lang->getLocale());

        // Straight to the description screen for a single upload: an image with
        // no alternative text is not publishable, and the moment it lands is
        // the moment the editor knows what it shows.
        if (1 === \count($stored)) {
            return new RedirectResponse($this->urls->generate('admin.cms.media.edit', [
                'id' => $stored[0]->getId(),
                self::EDIT_LANGUAGE_PARAMETER => $lang->getId(),
            ]));
        }

        return $this->backToList($request, $lang, $this->translator->trans(
            '%count% images added. Describe each of them before using it in a page.',
            ['%count%' => \count($stored)],
            TheliaCMS::DOMAIN_NAME,
        ));
    }

    #[Route('/{id}', name: 'edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $lang = $this->editLang($request);
        $locale = $lang->getLocale();
        $image = $this->ownedImageOrFail($id);

        $form = $this->buildForm($image, $locale);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyUnless(AccessManager::UPDATE);
            $this->writer->save($image, $locale, $form->getData());

            return new RedirectResponse($this->urls->generate('admin.cms.media.edit', [
                'id' => $id,
                self::EDIT_LANGUAGE_PARAMETER => $lang->getId(),
            ]));
        }

        return new Response($this->twig->render(self::EDIT_TEMPLATE, [
            'form' => $form->createView(),
            'item' => $this->catalog->item($image, $locale),
            'usages' => $this->usages->pagesUsing($id),
            'edit_locale' => $locale,
            'edit_language_id' => $lang->getId(),
        ]));
    }

    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): RedirectResponse
    {
        $this->denyUnless(AccessManager::DELETE);
        $lang = $this->editLang($request);
        $image = $this->ownedImageOrFail($id);

        $usageCount = \count($this->usages->pagesUsing($id));

        if ($usageCount > 0) {
            return $this->backToList($request, $lang, $this->translator->trans(
                'This image is still used by %count% page(s). Remove it from them first.',
                ['%count%' => $usageCount],
                TheliaCMS::DOMAIN_NAME,
            ), 'warning');
        }

        $this->writer->delete($image);

        return $this->backToList($request, $lang, $this->translator->trans('Image deleted.', [], TheliaCMS::DOMAIN_NAME));
    }

    private function buildForm(LibraryImage $image, string $locale): FormInterface
    {
        $image->setLocale($locale);

        return $this->forms->create(CmsMediaType::class, [
            'title' => $image->getTitle(),
            'alt' => $image->getAlt(),
            'caption' => $image->getCaption(),
            'decorative' => 1 === $image->getDecorative(),
            'tags' => implode(', ', $this->library->tagsOf($image)),
            'file' => null,
        ]);
    }

    private function ownedImageOrFail(int $id): LibraryImage
    {
        $image = $this->library->ownedImage($id);

        if (!$image instanceof LibraryImage) {
            throw new NotFoundHttpException();
        }

        return $image;
    }

    private function denyUnless(string $access): void
    {
        if (!$this->securityContext->isGranted(['ADMIN'], [CmsResources::MEDIA], [], [$access])) {
            throw new AccessDeniedHttpException($this->translator->trans('You are not allowed to change CMS media.', [], TheliaCMS::DOMAIN_NAME));
        }
    }

    private function editLang(Request $request): Lang
    {
        return $this->languages->resolve($request);
    }

    private function backToList(Request $request, Lang $lang, ?string $message = null, string $level = 'success'): RedirectResponse
    {
        $session = $request->hasSession() ? $request->getSession() : null;

        if (null !== $message && $session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($level, $message);
        }

        return new RedirectResponse($this->urls->generate('admin.cms.media.list', [
            self::EDIT_LANGUAGE_PARAMETER => $lang->getId(),
        ]));
    }
}
