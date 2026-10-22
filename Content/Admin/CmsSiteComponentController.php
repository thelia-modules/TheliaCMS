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

namespace TheliaCMS\Content\Admin;

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
use TheliaCMS\Content\SectionAvailability;
use TheliaCMS\Content\Site\InvalidSiteComponentException;
use TheliaCMS\Content\Site\SiteComponent;
use TheliaCMS\Content\Site\SiteComponentRepository;
use TheliaCMS\Content\Site\SiteComponentValidator;
use TheliaCMS\Content\Site\SitePropReader;
use TheliaCMS\Generation\Component\ComponentCandidates;
use TheliaCMS\Generation\Component\ComponentGenerator;
use TheliaCMS\Generation\Component\ComponentRequest;
use TheliaCMS\Generation\GenerationException;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Model\CmsComponentQuery;
use TheliaCMS\Preview\ComponentPreviewLink;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * The components the site makes in the back office: asked for in a prompt,
 * looked at in the theme, changed by asking again, then kept.
 *
 * A candidate is a row of the ledger: what the generator wrote, never saved
 * anywhere else until the editor keeps it. Seeing them takes the settings
 * permission (CmsAdminGuard holds the path to it); making, changing and
 * keeping one takes the custom code permission too, since a component writes
 * markup and styles shown on every page that uses it.
 */
#[Route('/admin/cms/components/site', name: 'admin.cms.components.site.')]
final readonly class CmsSiteComponentController
{
    private const string TEMPLATES = '@TheliaCMSModule/backOffice/default-twig/components/site/';

    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private SecurityContext $securityContext,
        private TranslatorInterface $translator,
        private ComponentGenerator $generator,
        private ComponentCandidates $candidates,
        private SiteComponentRepository $components,
        private SiteComponentValidator $validator,
        private SitePropReader $props,
        private SectionAvailability $availability,
        private SectionUsageFinder $usage,
        private ComponentPreviewLink $previews,
    ) {
    }

    #[Route('/new', name: 'new', methods: ['GET'])]
    public function new(): Response
    {
        return $this->render('new.html.twig', [
            'may_make' => $this->mayMake(),
        ]);
    }

    /**
     * A new component, a change of a candidate (`candidate`, the id of its
     * row of the ledger) or a change of a kept component (`component`, its
     * code). Every call ends on the screen of its candidate, which reads the
     * outcome from the ledger, a failure included.
     */
    #[Route('/generate', name: 'generate', methods: ['POST'])]
    public function generate(Request $request): Response
    {
        $this->denyUnlessMayMake();

        $text = trim((string) $request->request->get('request', ''));
        $back = $this->backTo($request);

        if ('' === $text) {
            $this->flash($request, 'danger', $this->translate('Say what the component is for.'));

            return new RedirectResponse($back);
        }

        $generation = $this->requestFor($request, $text);

        // A call is as long as the model takes; the limit of a request of the
        // back office is not meant for it.
        set_time_limit(0);

        try {
            $result = $this->generator->generate($generation);
        } catch (GenerationException $exception) {
            if (null === $exception->logId) {
                $this->flash($request, 'danger', $exception->getMessage());

                return new RedirectResponse($back);
            }

            return new RedirectResponse($this->urls->generate('admin.cms.components.site.candidate', ['id' => $exception->logId]));
        }

        return new RedirectResponse($this->urls->generate('admin.cms.components.site.candidate', ['id' => $result->logId]));
    }

    #[Route('/candidates/{id}', name: 'candidate', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function candidate(int $id, Request $request): Response
    {
        $candidate = $this->candidates->find($id) ?? throw new NotFoundHttpException();
        $kept = $candidate->componentId > 0 ? CmsComponentQuery::create()->findPk($candidate->componentId)?->getCode() : null;

        return $this->render('candidate.html.twig', [
            'candidate' => $candidate,
            'component' => $candidate->component,
            'props' => null === $candidate->component ? [] : $this->propRows($candidate->component),
            'preview_url' => null === $candidate->component ? null : $this->previews->candidate($candidate->logId, $request->getLocale()),
            'kept_code' => $kept,
            'pages' => null === $kept ? 0 : ($this->usage->pageCounts()['Site:'.$kept] ?? 0),
            'may_make' => $this->mayMake(),
            'failed' => GenerationLog::STATUS_DONE !== $candidate->status,
        ]);
    }

    #[Route('/candidates/{id}/keep', name: 'keep', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function keep(int $id, Request $request): Response
    {
        $this->denyUnlessMayMake();

        $candidate = $this->candidates->find($id) ?? throw new NotFoundHttpException();
        $screen = $this->urls->generate('admin.cms.components.site.candidate', ['id' => $id]);

        if (null === $candidate->component) {
            throw new NotFoundHttpException();
        }

        $replacing = $candidate->componentId > 0 ? CmsComponentQuery::create()->findPk($candidate->componentId)?->getCode() : null;

        try {
            $component = $this->validator->validate($candidate->component);
            $this->components->save($component, SiteComponentRepository::STATUS_READY, $replacing);
        } catch (InvalidSiteComponentException $exception) {
            $this->flash($request, 'danger', implode(' ', $exception->problems));

            return new RedirectResponse($screen);
        }

        $componentId = (int) CmsComponentQuery::create()->findOneByCode($component->code)?->getId();

        if (null === $replacing) {
            $this->candidates->attach([$candidate->logId], $componentId);
        }

        $this->flash($request, 'success', $this->translator->trans(
            null === $replacing ? '"%label%" is a component of the site: pages may use it, and the generator is offered it.' : '"%label%" is changed. Drafts drawn with it change at their next save; published pages at their next publication.',
            ['%label%' => $component->label],
            TheliaCMS::DOMAIN_NAME,
        ));

        return new RedirectResponse($this->urls->generate('admin.cms.components.site.show', ['code' => $component->code]));
    }

    #[Route('/{code}', name: 'show', requirements: ['code' => '[A-Z][A-Za-z0-9]{1,39}'], methods: ['GET'])]
    public function show(string $code, Request $request): Response
    {
        $component = $this->components->find($code) ?? throw new NotFoundHttpException();
        $id = (int) CmsComponentQuery::create()->findOneByCode($code)?->getId();

        return $this->render('component.html.twig', [
            'component' => $component,
            'props' => $this->propRows($component),
            'preview_url' => $this->previews->component($code, $request->getLocale()),
            'history' => $this->candidates->history($id),
            'pages' => $this->usage->pageCounts()[$component->name()] ?? 0,
            'offered' => $this->availability->isOffered($component->name()),
            'may_make' => $this->mayMake(),
        ]);
    }

    private function requestFor(Request $request, string $text): ComponentRequest
    {
        $locale = $request->getLocale();
        $code = (string) $request->request->get('component', '');

        if ('' !== $code) {
            $component = $this->components->find($code) ?? throw new NotFoundHttpException();

            return new ComponentRequest($text, $locale, $component, (int) CmsComponentQuery::create()->findOneByCode($code)?->getId());
        }

        $candidateId = (int) $request->request->get('candidate', 0);

        if ($candidateId > 0) {
            $candidate = $this->candidates->find($candidateId);

            if (null === $candidate?->component) {
                throw new NotFoundHttpException();
            }

            return new ComponentRequest($text, $locale, $candidate->component, $candidate->componentId > 0 ? $candidate->componentId : null);
        }

        return new ComponentRequest($text, $locale);
    }

    /**
     * Where a request that cannot run sends the editor back: the screen it
     * came from.
     */
    private function backTo(Request $request): string
    {
        $code = (string) $request->request->get('component', '');
        $candidateId = (int) $request->request->get('candidate', 0);

        return match (true) {
            1 === preg_match('/^[A-Z][A-Za-z0-9]{1,39}$/', $code) => $this->urls->generate('admin.cms.components.site.show', ['code' => $code]),
            $candidateId > 0 => $this->urls->generate('admin.cms.components.site.candidate', ['id' => $candidateId]),
            default => $this->urls->generate('admin.cms.components.site.new'),
        };
    }

    /**
     * The props as a table of the screen: name, type, label, help, required.
     *
     * @return list<array{name: string, type: string, label: string, help: string|null, required: bool, items: list<string>}>
     */
    private function propRows(SiteComponent $component): array
    {
        try {
            $schema = $this->props->read($component->props);
        } catch (InvalidSiteComponentException) {
            return [];
        }

        $rows = [];

        foreach ($schema->props as $prop) {
            $rows[] = [
                'name' => $prop->name,
                'type' => $prop->type->value,
                'label' => $prop->label,
                'help' => $prop->help,
                'required' => $prop->required,
                'items' => array_map(static fn ($item): string => $item->name.' ('.$item->type->value.')', $prop->items),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function render(string $template, array $parameters): Response
    {
        return new Response($this->twig->render(self::TEMPLATES.$template, $parameters));
    }

    private function mayMake(): bool
    {
        return $this->securityContext->isGranted(['ADMIN'], [CmsResources::SETTINGS], [], [AccessManager::UPDATE])
            && $this->securityContext->isGranted(['ADMIN'], [CmsResources::CUSTOM_CODE], [], [AccessManager::UPDATE]);
    }

    private function denyUnlessMayMake(): void
    {
        if (!$this->mayMake()) {
            throw new AccessDeniedHttpException($this->translate('Making a component takes the settings and the custom code permissions.'));
        }
    }

    private function flash(Request $request, string $type, string $message): void
    {
        if ($request->hasSession() && method_exists($session = $request->getSession(), 'getFlashBag')) {
            $session->getFlashBag()->add($type, $message);
        }
    }

    private function translate(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
