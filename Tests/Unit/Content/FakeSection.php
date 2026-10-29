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

namespace TheliaCMS\Tests\Unit\Content;

use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Content\SectionDefinitionInterface;

/**
 * A component with one required prop and one with a default, enough to show
 * normalisation and rendering on something other than free markup.
 */
final readonly class FakeSection implements SectionDefinitionInterface
{
    public const string NAME = 'Test:Fake';

    public function name(): string
    {
        return self::NAME;
    }

    public function label(): string
    {
        return 'Fake';
    }

    public function description(): string
    {
        return 'A heading at a level, for tests.';
    }

    public function schema(): PropSchema
    {
        return new PropSchema([
            Prop::text('title', 'Title', required: true),
            Prop::integer('level', 'Level', 2, 1, 6),
        ]);
    }

    public function sample(): array
    {
        return ['title' => 'A heading', 'level' => 2];
    }

    public function normalizeProps(array $props): array
    {
        $title = $props['title'] ?? null;

        if (!\is_string($title)) {
            throw InvalidDocumentException::because(['The title must be a string.']);
        }

        return ['title' => $title, 'level' => (int) ($props['level'] ?? 2)];
    }

    public function render(array $props, string $locale): string
    {
        return \sprintf('<h%d lang="%s">%s</h%d>', $props['level'], $locale, htmlspecialchars($props['title']), $props['level']);
    }
}
