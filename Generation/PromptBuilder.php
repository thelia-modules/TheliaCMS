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

namespace TheliaCMS\Generation;

use TheliaCMS\Page\Field\FieldDefinition;
use TheliaCMS\Page\Field\FieldKind;
use TheliaCMS\Skill\Skill;
use TheliaCMS\Skill\SkillRepository;

/**
 * What the model is told, in two parts.
 *
 * The system prompt is the same for every call of the site: the role, the
 * skills, the catalogue. It is written in a stable order and its last block
 * asks the API to cache it, so a site pays for it once an hour rather than
 * once a page. Everything that changes — the shop, the page, the brief — comes
 * after, in the user message.
 */
final readonly class PromptBuilder
{
    public function __construct(
        private SkillRepository $skills,
        private CatalogSheet $catalog,
        private ShopContext $shop,
        private GeneratorSettings $settings,
    ) {
    }

    /**
     * The system blocks, the last one cached.
     *
     * @return list<array{type: 'text', text: string, cacheControl?: array{type: 'ephemeral'}}>
     */
    public function system(): array
    {
        $blocks = [
            ['type' => 'text', 'text' => self::role()],
            ['type' => 'text', 'text' => $this->skillsText()],
            ['type' => 'text', 'text' => $this->catalog->text(), 'cacheControl' => ['type' => 'ephemeral']],
        ];

        return $blocks;
    }

    /**
     * The user message of a request: context, then what is asked.
     */
    public function userMessage(GenerationRequest $request): string
    {
        $shop = $this->shop->describe($request->locale);
        $target = $request->target;

        $lines = [
            '# The site',
            '',
            \sprintf('- Name: %s', '' !== $shop['store_name'] ? $shop['store_name'] : '(not set)'),
        ];

        if ('' !== $shop['store_description']) {
            $lines[] = \sprintf('- About: %s', $shop['store_description']);
        }

        $lines[] = \sprintf('- Language of the page: %s (`%s`). Write everything in it.', $shop['language'], $shop['locale']);

        if ('' !== $shop['currency']) {
            $lines[] = \sprintf('- Currency: %s', $shop['currency']);
        }

        $lines[] = '';

        $lines[] = '# The page';
        $lines[] = '';
        $lines[] = \sprintf('- Title: %s', $target->title);
        $lines[] = \sprintf('- Type: %s', (string) $target->pageType);

        if (null !== $target->summary) {
            $lines[] = \sprintf('- Summary written by the editor: %s', $target->summary);
        }

        $lines[] = '';

        array_push($lines, ...$this->typeLines($request));

        // The brief of the page wins; the brief of its type says how every
        // page of the type sounds when the page says nothing.
        $typeBrief = $target->typeBrief();
        $tone = $request->brief->tone ?? $typeBrief?->tone;
        $audience = $request->brief->audience ?? $typeBrief?->audience;

        if (null !== $tone) {
            $lines[] = \sprintf('Tone asked for: %s', $tone);
        }

        if (null !== $audience) {
            $lines[] = \sprintf('Audience: %s', $audience);
        }

        if (null !== $request->current) {
            $lines[] = GenerationRequest::KIND_TRANSLATION === $request->kind
                ? \sprintf('# The document the page holds in %s (`%s`)', $this->shop->describe((string) $request->sourceLocale)['language'], (string) $request->sourceLocale)
                : '# The document the page holds today';
            $lines[] = '';
            $lines[] = '```json';
            $lines[] = $request->current->toJson();
            $lines[] = '```';
            $lines[] = '';
        }

        $lines[] = match ($request->kind) {
            GenerationRequest::KIND_BRIEF => "# The brief\n\nWrite the whole page from this brief:\n\n".$request->brief->text,
            GenerationRequest::KIND_TRANSLATION => \sprintf("# The task\n\nWrite this document in %s (`%s`): the same sections in the same order with the same ids and components, every text written for a reader of that language rather than word for word, and every image, reference, address and figure kept as it is. Change nothing else.", $shop['language'], $shop['locale']),
            GenerationRequest::KIND_REVISION => "# The change asked\n\nAnswer with the whole document again, changed as asked and nothing else: keep every section the change does not concern exactly as it is, ids included.\n\n".$request->brief->text,
            GenerationRequest::KIND_SECTION => \sprintf("# The change asked\n\nWrite the section `%s` again as asked, and answer with the whole document: every other section exactly as it is, ids included.\n\n%s", $request->sectionId, $request->brief->text),
            default => $request->brief->text,
        };

        if ([] !== $addresses = $this->readableAddresses($request)) {
            $lines[] = '';
            $lines[] = '# The pages the brief cites';
            $lines[] = '';
            $lines[] = 'Read each of them with the `web_fetch` tool before you write: what they say counts as given by the brief. A page that cannot be read is left out, and the document says nothing it would have said.';
            $lines[] = '';

            foreach ($addresses as $address) {
                $lines[] = '- '.$address;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * What the type of the page says about every page of it, and the fields
     * the answer writes. Nothing for a type that is only a code: the request
     * of such a page stays the one it was.
     *
     * @return list<string>
     */
    private function typeLines(GenerationRequest $request): array
    {
        $target = $request->target;
        $brief = $target->typeBrief();
        $fields = $request->fieldsAsked();

        $model = GenerationRequest::KIND_BRIEF === $request->kind ? $target->modelDocument : null;

        if (null === $brief && $fields->isEmpty() && null === $model) {
            return [];
        }

        $lines = [\sprintf('# The type of the page: %s', $target->typeTitle()), ''];

        if (null !== $brief) {
            $lines[] = 'Every page of this type is written from this brief, before the brief of the page itself:';
            $lines[] = '';
            $lines[] = $brief->text;
            $lines[] = '';
        }

        if (null !== $model) {
            $lines[] = 'The model of the type, the demo every page of it starts from. Follow its structure — the kinds of sections and their order — and write everything in it again for this page: its texts, images and references are examples, not content of this page.';
            $lines[] = '';
            $lines[] = '```json';
            $lines[] = $model->toJson();
            $lines[] = '```';
            $lines[] = '';
        }

        if (!$fields->isEmpty()) {
            $lines[] = 'Pages of this type carry these fields. Write their values in `fields`, a JSON object by code written as a string; a value the brief gives no ground for is left out, never invented. A list of the pages of the type sorts and filters on them.';
            $lines[] = '';

            foreach ($fields as $field) {
                $lines[] = $this->fieldLine($field, $target->fieldValues[$field->code] ?? null, $request->locale);
            }

            $lines[] = '';
        }

        return $lines;
    }

    private function fieldLine(FieldDefinition $field, int|float|string|bool|null $value, string $locale): string
    {
        $kind = match ($field->kind) {
            FieldKind::Integer => 'a whole number',
            FieldKind::Decimal => 'a number',
            FieldKind::Duration => 'a whole number of minutes',
            FieldKind::Text => 'one line of plain text',
            FieldKind::LongText => 'plain text, no HTML',
            FieldKind::Boolean => 'true or false',
            FieldKind::Choice => 'one of '.implode(', ', array_map(static fn (string $choice): string => '"'.$choice.'"', $field->choices)),
            FieldKind::Date => 'a date written YYYY-MM-DD',
        };

        $notes = array_filter([
            $kind,
            null === $field->unit ? null : 'in '.$field->unit,
            null === $field->min ? null : 'at least '.$field->min,
            null === $field->max ? null : ($field->kind->isNumber() ? 'at most '.$field->max : 'at most '.$field->max.' characters'),
            $field->required ? 'required' : null,
        ]);

        $line = \sprintf('- `%s`: %s (%s)', $field->code, $field->label($locale), implode(', ', $notes));

        return null === $value ? $line : $line.'. The page holds: '.json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    /**
     * The web pages the model may read for this request: the ones its brief
     * cites, unless the site turned the reading off. A translation has no
     * brief of the editor's to cite any.
     *
     * @return list<string>
     */
    public function readableAddresses(GenerationRequest $request): array
    {
        if (!$this->settings->webFetch() || GenerationRequest::KIND_TRANSLATION === $request->kind) {
            return [];
        }

        return $request->brief->addresses();
    }

    /**
     * The message that sends a document back for correction.
     *
     * @param list<string> $problems
     */
    public static function correction(array $problems): string
    {
        return "The document you answered with cannot be stored. Fix every problem below and answer with the whole document again:\n\n- ".implode("\n- ", $problems);
    }

    private static function role(): string
    {
        return <<<'TEXT'
            You write the content of pages for the website of a shop, as structured documents the site renders itself.

            You are given the skills that say how a page is written here, the catalogue of components a page may be made of, then the site, the page and the brief. Read the brief, use the tools to find what the site holds — products, categories, pages, images — when a section needs them, and answer with one JSON document matching the schema you are constrained to. The skills are rules, not suggestions. Invent no fact the brief did not give.
            TEXT;
    }

    private function skillsText(): string
    {
        $always = [];
        $onDemand = [];

        foreach ($this->skills->enabled() as $skill) {
            if ($skill->always) {
                $always[] = $skill;
            } else {
                $onDemand[] = $skill;
            }
        }

        $lines = ['# Skills', ''];

        foreach ($always as $skill) {
            $lines[] = $skill->body;
            $lines[] = '';
        }

        if ([] !== $onDemand) {
            $lines[] = 'Other skills, read with the `read_skill` tool when the page calls for them:';
            $lines[] = '';

            foreach ($onDemand as $skill) {
                $lines[] = \sprintf('- `%s`: %s', $skill->code, $skill->description);
            }

            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<Skill>
     */
    public function skillsOnDemand(): array
    {
        return array_values(array_filter($this->skills->enabled(), static fn (Skill $skill): bool => !$skill->always));
    }
}
