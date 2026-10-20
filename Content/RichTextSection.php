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

/**
 * A piece of free markup, as is.
 *
 * It is the one component that is not a component of the theme: what a seeded
 * legal page holds, what an editor types in a rich-text field, what an import
 * written before the catalogue existed carries. The markup is printed as it
 * stands here; filtering it is the job of the writer, on the rendered page,
 * the same way it filters everything that goes online.
 */
final readonly class RichTextSection implements SectionDefinitionInterface
{
    public const string NAME = 'cms:richtext';

    public function name(): string
    {
        return self::NAME;
    }

    public function normalizeProps(array $props): array
    {
        $html = $props['html'] ?? '';

        if (!\is_string($html)) {
            throw InvalidDocumentException::because(['A rich text section needs its "html" to be a string.']);
        }

        return ['html' => $html];
    }

    public function render(array $props, string $locale): string
    {
        return (string) ($props['html'] ?? '');
    }
}
