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

namespace TheliaCMS\Script;

/**
 * Where in the document a snippet is written.
 *
 * Vendors are specific about this and rarely say why: a measurement tag placed
 * at the end of the body misses the events fired while the page loads, and a
 * widget placed in the head runs before the element it looks for exists.
 */
enum ScriptPlacement: string
{
    case Head = 'head';
    case BodyTop = 'body_top';
    case BodyBottom = 'body_bottom';

    public static function fromStorage(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Head;
    }

    /** The theme hook point that renders this placement. */
    public function hook(): string
    {
        return match ($this) {
            // Not `layout.head.top`: that is where the consent banner and the
            // consent defaults go, and they have to come before every tag.
            self::Head => 'layout.head.bottom',
            self::BodyTop => 'layout.body.top',
            self::BodyBottom => 'layout.body.bottom',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Head => 'In the head',
            self::BodyTop => 'Right after the page opens',
            self::BodyBottom => 'At the end of the page',
        };
    }

    public function help(): string
    {
        return match ($this) {
            self::Head => 'Runs before the page is drawn. What most measurement tags ask for.',
            self::BodyTop => 'Runs as the page starts. Where a noscript fallback belongs.',
            self::BodyBottom => 'Runs once the page is there. The polite place for a widget.',
        };
    }

    /**
     * @return array<string, string> label => value, for a choice field
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case->value;
        }

        return $choices;
    }
}
