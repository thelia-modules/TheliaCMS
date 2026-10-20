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

namespace TheliaCMS\Generation\Admin;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\SecurityContext;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\Section;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Generation\ClaudeClientProviderInterface;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\GenerationUnavailableException;
use TheliaCMS\Generation\GeneratorSettings;
use TheliaCMS\Model\CmsPageGeneration;
use TheliaCMS\Security\CmsResources;
use TheliaCMS\TheliaCMS;
use Twig\Environment;

/**
 * The content screen: a brief and the generator on the left, the draft on
 * the right. Everything the template reads is shaped here, so the
 * controller only says which page it is about.
 */
final readonly class ContentScreen
{
    private const string TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/content/edit.html.twig';

    /** How much of a section is quoted in the list of sections. */
    private const int EXCERPT_LENGTH = 110;

    public function __construct(
        private Environment $twig,
        private FormFactoryInterface $forms,
        private TranslatorInterface $translator,
        private SecurityContext $securityContext,
        private SectionCatalog $catalog,
        private GenerationLog $log,
        private GeneratorSettings $settings,
        private ClaudeClientProviderInterface $clients,
        private SectionFormBuilder $sectionForms,
        private ToolkitLink $toolkit,
    ) {
    }

    public function render(Request $request, ContentSubject $subject): string
    {
        $mayGenerate = $this->securityContext->isGranted(['ADMIN'], [CmsResources::GENERATE], [], [AccessManager::UPDATE]);
        $mayPublish = $this->securityContext->isGranted(['ADMIN'], [CmsResources::PAGE], [], [AccessManager::UPDATE]);
        $document = $subject->document;

        return $this->twig->render(self::TEMPLATE, [
            'subject' => $subject,
            'brief_form' => $this->forms->create(BriefType::class, $this->lastBrief($subject), ['action' => $subject->url('generate')])->createView(),
            'change_form' => $this->forms->create(ChangeType::class, null, ['action' => $subject->url('revise')])->createView(),
            'translate_form' => $this->translateForm($subject),
            'sections' => null === $document ? [] : $this->sections($document, $subject, $mayPublish),
            'history' => array_map($this->row(...), $this->log->history($subject->target, $subject->locale, 20)),
            'outcome' => $this->outcome($request, $subject),
            'may_generate' => $mayGenerate,
            'may_publish' => $mayPublish,
            'generator_notice' => $mayGenerate ? $this->notice() : null,
            'model' => $this->settings->model(),
            'toolkit_url' => $this->toolkit->url(),
            'assets_version' => self::assetsVersion(),
        ]);
    }

    /**
     * The brief the target was last written from, back in its form: a page
     * is written again from its own brief, corrected, rather than from a
     * blank.
     *
     * @return array{text: string, tone: string|null, audience: string|null}|null
     */
    private function lastBrief(ContentSubject $subject): ?array
    {
        $brief = $this->log->latestBrief($subject->target, $subject->locale);

        return null === $brief ? null : ['text' => $brief->text, 'tone' => $brief->tone, 'audience' => $brief->audience];
    }

    /**
     * The form that writes this language from another, offered when the
     * target holds nothing here and a draft elsewhere. The first language
     * in the order of the shop is picked by default.
     */
    private function translateForm(ContentSubject $subject): ?FormView
    {
        if ($subject->hasContent() || [] === $subject->translationSources) {
            return null;
        }

        return $this->forms
            ->create(TranslateType::class, ['source' => $subject->translationSources[0]['locale']], [
                'action' => $subject->url('translate'),
                'sources' => array_column($subject->translationSources, 'locale', 'label'),
            ])
            ->createView();
    }

    /**
     * Changes with the script and the stylesheet of the screen, so an updated
     * module is not shadowed by what the browser already holds.
     */
    private static function assetsVersion(): string
    {
        $assets = __DIR__.'/../../templates/backOffice/default-twig/assets/';

        return substr(hash('xxh3', (string) @filemtime($assets.'content-editor.js').(string) @filemtime($assets.'content-editor.css')), 0, 12);
    }

    /**
     * @param bool $mayEdit whether the form to change the props by hand is offered
     *
     * @return list<array{id: string, rank: int, component: string, label: string, excerpt: string, form: FormView, edit: FormView|null}>
     */
    private function sections(Document $document, ContentSubject $subject, bool $mayEdit): array
    {
        $sections = [];

        foreach ($document->sections as $index => $section) {
            $definition = $this->catalog->find($section->component);

            $sections[] = [
                'id' => $section->id,
                'rank' => $index + 1,
                'component' => $section->component,
                'label' => $definition?->label() ?? $section->component,
                'excerpt' => self::excerpt($section),
                // Changing a word by hand costs nothing: the right to change
                // the page is enough, where the generator takes its own.
                'edit' => $mayEdit && null !== $definition ? $this->sectionForms->build($definition, $section, $subject->url('update'))->createView() : null,
                'form' => $this->forms
                    ->createNamed(self::sectionFormName($section->id), ChangeType::class, ['section' => $section->id], ['section' => true, 'action' => $subject->url('section')])
                    ->createView(),
            ];
        }

        return $sections;
    }

    /**
     * The form of a section is named after it: two sections on one screen
     * never share a field id, which a label has to point at.
     */
    public static function sectionFormName(string $sectionId): string
    {
        return 'cms_change_'.preg_replace('/[^a-z0-9_]/i', '_', $sectionId);
    }

    /**
     * The name of the section form a request posted, read from its fields.
     */
    public static function postedSectionForm(Request $request): string
    {
        foreach (array_keys($request->request->all()) as $name) {
            if (\is_string($name) && str_starts_with($name, 'cms_change_')) {
                return $name;
            }
        }

        return 'cms_change_none';
    }

    /**
     * The first words a reader would recognise the section by: its title,
     * else its text, else the first text of its items.
     */
    private static function excerpt(Section $section): string
    {
        foreach (['title', 'text', 'html', 'quote', 'label'] as $name) {
            $value = $section->props[$name] ?? null;

            if (\is_string($value) && '' !== trim(strip_tags($value))) {
                return self::shorten($value);
            }
        }

        foreach ($section->props as $value) {
            if (\is_array($value) && \is_array($value[0] ?? null)) {
                foreach ($value[0] as $itemValue) {
                    if (\is_string($itemValue) && '' !== trim(strip_tags($itemValue))) {
                        return self::shorten($itemValue);
                    }
                }
            }
        }

        return '';
    }

    private static function shorten(string $html): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5)));

        return mb_strlen($text) > self::EXCERPT_LENGTH ? rtrim(mb_substr($text, 0, self::EXCERPT_LENGTH)).'…' : $text;
    }

    /**
     * @return array{id: int, date: \DateTimeInterface|null, kind: string, status: string, status_class: string, cost: string, duration: string, turns: int, error: string|null}
     */
    private function row(CmsPageGeneration $row): array
    {
        return [
            'id' => (int) $row->getId(),
            'date' => $row->getCreatedAt(),
            'kind' => $this->kindLabel((string) $row->getKind()),
            'status' => $this->statusLabel((string) $row->getStatus()),
            'status_class' => match ($row->getStatus()) {
                GenerationLog::STATUS_DONE => 'text-bg-success',
                GenerationLog::STATUS_REFUSED, GenerationLog::STATUS_INVALID => 'text-bg-warning',
                default => 'text-bg-danger',
            },
            'cost' => number_format((float) $row->getCostUsd(), 3, '.', ''),
            'duration' => number_format(((int) $row->getDurationMs()) / 1000, 1, '.', ''),
            'turns' => (int) $row->getTurns(),
            'error' => $row->getError(),
        ];
    }

    /**
     * The outcome of the call the screen was reloaded after, read from the
     * ledger: a stream cannot leave a flash message behind, and the ledger
     * says more than a flash would.
     *
     * @return array{type: string, message: string, details: list<string>}|null
     */
    private function outcome(Request $request, ContentSubject $subject): ?array
    {
        $id = $request->query->getInt(GenerationRunner::OUTCOME);

        if ($id <= 0) {
            return null;
        }

        $row = $this->log->find($id, $subject->target, $subject->locale);

        if (null === $row) {
            return null;
        }

        $figures = $this->translator->trans('%turns% turn(s), %duration% s, %cost% USD', [
            '%turns%' => (int) $row->getTurns(),
            '%duration%' => number_format(((int) $row->getDurationMs()) / 1000, 1, '.', ''),
            '%cost%' => number_format((float) $row->getCostUsd(), 3, '.', ''),
        ], TheliaCMS::DOMAIN_NAME);

        return match ($row->getStatus()) {
            GenerationLog::STATUS_DONE => [
                'type' => 'success',
                'message' => $this->translator->trans('The draft is written (%figures%). Read it, ask for changes, then publish it.', ['%figures%' => $figures], TheliaCMS::DOMAIN_NAME),
                'details' => [],
            ],
            GenerationLog::STATUS_REFUSED => [
                'type' => 'warning',
                'message' => $this->translator->trans('The model declined to write this (%figures%). Reword the brief.', ['%figures%' => $figures], TheliaCMS::DOMAIN_NAME),
                'details' => [],
            ],
            GenerationLog::STATUS_INVALID => [
                'type' => 'warning',
                'message' => $this->translator->trans('The model answered with a document the site cannot store, twice (%figures%). Nothing was changed; try again or reword the brief.', ['%figures%' => $figures], TheliaCMS::DOMAIN_NAME),
                'details' => [(string) $row->getError()],
            ],
            default => [
                'type' => 'danger',
                'message' => $this->translator->trans('The generation failed (%figures%). Nothing was changed.', ['%figures%' => $figures], TheliaCMS::DOMAIN_NAME),
                'details' => [(string) $row->getError()],
            ],
        };
    }

    private function notice(): ?string
    {
        try {
            $this->clients->client();
        } catch (GenerationUnavailableException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    private function kindLabel(string $kind): string
    {
        return $this->translator->trans(match ($kind) {
            GenerationRequest::KIND_BRIEF => 'Brief',
            GenerationRequest::KIND_REVISION => 'Change',
            GenerationRequest::KIND_SECTION => 'Section',
            GenerationRequest::KIND_TRANSLATION => 'Translation',
            GenerationLog::KIND_MANUAL => 'By hand',
            default => $kind,
        }, [], TheliaCMS::DOMAIN_NAME);
    }

    private function statusLabel(string $status): string
    {
        return $this->translator->trans(match ($status) {
            GenerationLog::STATUS_DONE => 'Written',
            GenerationLog::STATUS_REFUSED => 'Declined',
            GenerationLog::STATUS_INVALID => 'Refused by the site',
            GenerationLog::STATUS_FAILED => 'Failed',
            default => $status,
        }, [], TheliaCMS::DOMAIN_NAME);
    }
}
