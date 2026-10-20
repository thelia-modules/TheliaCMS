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

        if ($target->isPage()) {
            $lines[] = '# The page';
            $lines[] = '';
            $lines[] = \sprintf('- Title: %s', $target->title);
            $lines[] = \sprintf('- Type: %s', (string) $target->pageType);

            if (null !== $target->summary) {
                $lines[] = \sprintf('- Summary written by the editor: %s', $target->summary);
            }
        } else {
            $lines[] = '# The block';
            $lines[] = '';
            $lines[] = \sprintf('- Name: %s', $target->title);

            if (null !== $target->summary) {
                $lines[] = \sprintf('- Shown on: %s', $target->summary);
            }

            $lines[] = '';
            $lines[] = 'This document is a block, not a page: a piece of content shown inside pages that have a main heading of their own. Give it no main heading (no `heading_level` 1, no `<h1>`), one to three sections, and nothing that only makes sense as a whole page.';
        }

        if ([] !== $target->excludedComponents) {
            $lines[] = \sprintf('- Never use these components here: %s.', implode(', ', array_map(static fn (string $name): string => '`'.$name.'`', $target->excludedComponents)));
        }

        $lines[] = '';

        if (null !== $request->brief->tone) {
            $lines[] = \sprintf('Tone asked for: %s', $request->brief->tone);
        }

        if (null !== $request->brief->audience) {
            $lines[] = \sprintf('Audience: %s', $request->brief->audience);
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

        return implode("\n", $lines);
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
