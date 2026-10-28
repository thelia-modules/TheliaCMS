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

namespace TheliaCMS\Generation\Text;

use TheliaCMS\Generation\ShopContext;
use TheliaCMS\Skill\SkillRepository;

/**
 * What the model is told to write the text of one rich-text field.
 *
 * The system prompt holds the role and the writing skill of the site, the one
 * that says how this site writes; the other skills are about pages made of
 * sections, and are listed for the model to read when a text calls for one.
 * The object, the field, what it holds and the brief come in the user message.
 */
final readonly class TextPrompt
{
    /** The skill every text is written by; the others are read on demand. */
    public const string WRITING_SKILL = 'copywriting';

    /** How much of what the field holds the model is given. */
    private const int CURRENT_LENGTH = 6000;

    public function __construct(
        private SkillRepository $skills,
        private ShopContext $shop,
    ) {
    }

    /**
     * @return list<array{type: 'text', text: string, cacheControl?: array{type: 'ephemeral'}}>
     */
    public function system(): array
    {
        return [
            ['type' => 'text', 'text' => self::role()],
            ['type' => 'text', 'text' => $this->skillsText(), 'cacheControl' => ['type' => 'ephemeral']],
        ];
    }

    public function userMessage(TextRequest $text): string
    {
        $request = $text->request;
        $shop = $this->shop->describe($request->locale);
        $current = trim($text->currentHtml);

        $lines = [
            '# The site',
            '',
            \sprintf('- Name: %s', '' !== $shop['store_name'] ? $shop['store_name'] : '(not set)'),
        ];

        if ('' !== $shop['store_description']) {
            $lines[] = \sprintf('- Description: %s', $shop['store_description']);
        }

        $lines[] = \sprintf('- Language of the text: %s (`%s`)', $shop['language'], $request->locale);
        $lines[] = '';
        $lines[] = '# What the text is for';
        $lines[] = '';
        $lines[] = \sprintf('- %s: %s', self::objectName($request->target->type), '' !== $request->target->title ? $request->target->title : '(no title yet)');
        $lines[] = '- '.$text->field->brief();
        $lines[] = '';

        if ('' !== $current) {
            $lines[] = '# What the field holds now';
            $lines[] = '';
            $lines[] = 'Rewrite it or complete it, as the brief says. Keep what the brief does not ask to change.';
            $lines[] = '';
            $lines[] = mb_substr($current, 0, self::CURRENT_LENGTH);
            $lines[] = '';
        }

        $lines[] = '# The brief';
        $lines[] = '';
        $lines[] = $request->brief->text;

        if (null !== $request->brief->tone) {
            $lines[] = '';
            $lines[] = 'Tone: '.$request->brief->tone;
        }

        if (null !== $request->brief->audience) {
            $lines[] = '';
            $lines[] = 'Audience: '.$request->brief->audience;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['html' => ['type' => 'string']],
            'required' => ['html'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param list<string> $problems
     */
    public static function correction(array $problems): string
    {
        return "The text you answered with cannot be used. Fix every problem below and answer again:\n\n- ".implode("\n- ", $problems);
    }

    private static function objectName(string $type): string
    {
        return match ($type) {
            'page' => 'Page',
            'product' => 'Product',
            'category' => 'Category of products',
            'brand' => 'Brand',
            'folder' => 'Folder of contents',
            'content' => 'Content',
            default => ucfirst($type),
        };
    }

    private static function role(): string
    {
        return <<<'TEXT'
            You write the text of one field of the website of a shop: a summary, a description or a conclusion, for a page or an item of its catalogue.

            Answer with one JSON object matching the schema you are constrained to: `html` holds the text, as HTML made of paragraphs (`p`), emphasis (`strong`, `em`), lists (`ul`, `ol`, `li`), links (`a href`), subheadings (`h2` to `h4`) and quotes (`blockquote`), and nothing else: no image, no table, no style, no class. Use the tools to find the products, categories and pages of the site when the text names or links to them, and link only to addresses a tool gave you. The skills are rules, not suggestions. Invent no fact the brief, the field or the tools did not give.
            TEXT;
    }

    private function skillsText(): string
    {
        $lines = ['# Skills', ''];
        $others = [];

        foreach ($this->skills->enabled() as $skill) {
            if (self::WRITING_SKILL === $skill->code) {
                $lines[] = $skill->body;
                $lines[] = '';

                continue;
            }

            $others[] = \sprintf('- `%s`: %s', $skill->code, $skill->description);
        }

        if ([] !== $others) {
            $lines[] = 'Other skills of the site, written for whole pages; read one with the `read_skill` tool when the text calls for it:';
            $lines[] = '';
            array_push($lines, ...$others);
        }

        return implode("\n", $lines);
    }
}
