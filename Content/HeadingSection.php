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

namespace TheliaCMS\Content;

use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Content\Theme\ThemeComponentSections;
use TheliaCMS\TheliaCMS;

/**
 * A heading on its own between two sections, at the level the editor
 * chooses: the page draws its title as the one `h1`, so the levels offered
 * start at `h2`, and the heading check of the publication reads it like any
 * other heading.
 */
final readonly class HeadingSection implements SectionDefinitionInterface
{
    public const string NAME = 'cms:heading';

    /** The levels offered, the first one by default. */
    public const array LEVELS = ['h2', 'h3', 'h4'];

    public function __construct(
        private ?TranslatorInterface $translator = null,
        private ?ThemeComponentSections $theme = null,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return $this->trans('Heading');
    }

    public function description(): string
    {
        return $this->trans('A heading on its own, between two sections: it opens a part of the page. Level 2 under the title of the page, level 3 under a heading of level 2, never a level skipped.');
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            Prop::text('text', $this->trans('Heading'), required: true, max: 120),
            Prop::choice('level', $this->trans('Level'), [
                'h2' => $this->trans('Level 2, a part of the page'),
                'h3' => $this->trans('Level 3, under a heading of level 2'),
                'h4' => $this->trans('Level 4, under a heading of level 3'),
            ], self::LEVELS[0], $this->trans('The title of the page is the only heading of level 1.')),
        ]);
    }

    public function sample(): array
    {
        return ['text' => $this->trans('A heading'), 'level' => self::LEVELS[0]];
    }

    public function normalizeProps(array $props): array
    {
        return $this->schema()->normalize($props);
    }

    public function render(array $props, string $locale): string
    {
        $text = trim((string) ($props['text'] ?? ''));

        if ('' === $text) {
            return '';
        }

        $level = \in_array($props['level'] ?? null, self::LEVELS, true) ? $props['level'] : self::LEVELS[0];
        // The class the theme styles its free text under: a heading of the
        // page looks like the headings of its text.
        $themeClass = $this->theme?->declaration()->richTextClass;
        $class = null === $themeClass ? 'cms-heading' : 'cms-heading '.$themeClass;

        return \sprintf(
            '<div class="%s"><%s class="cms-heading__text">%s</%2$s></div>',
            htmlspecialchars($class, \ENT_QUOTES | \ENT_HTML5),
            $level,
            htmlspecialchars($text, \ENT_QUOTES | \ENT_HTML5),
        );
    }

    private function trans(string $message): string
    {
        return $this->translator?->trans($message, [], TheliaCMS::DOMAIN_NAME) ?? $message;
    }
}
