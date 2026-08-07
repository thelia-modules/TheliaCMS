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

namespace TheliaCMS\Partial;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A block whose content is produced by the server every time the page is
 * served: the latest news, a menu, a reusable block, a click-to-load embed.
 *
 * A page stores nothing but the name of the partial and its settings; the
 * markup is never in the database, so what visitors get follows the site
 * rather than the day the page was published.
 *
 * This is the extension point of the module: any bundle or module can add a
 * partial by implementing this interface — the tag is applied automatically,
 * and the name it returns is the only thing an editor ever writes in a page.
 */
#[AutoconfigureTag(PartialRegistry::TAG)]
interface PartialDefinitionInterface
{
    /**
     * Identifier written in `data-cms-partial`. Lower-case, dash-separated.
     */
    public function name(): string;

    /**
     * Label of the block in the editor, translated.
     */
    public function label(): string;

    /**
     * Template the active front-office theme may ship to take the rendering
     * over, relative to its root and without the extension — for instance
     * `cms/partials/latest-contents`.
     */
    public function themeTemplate(): string;

    /**
     * Template used when the theme provides none. A namespaced Twig path, so a
     * partial contributed by another module points at its own templates.
     */
    public function fallbackTemplate(): string;

    /**
     * Settings the block exposes, in the order the editor shows them.
     *
     * @return list<PartialProp>
     */
    public function props(): array;

    /**
     * Variables handed to the template.
     *
     * Props arrive validated against {@see props()}: a template never has to
     * defend itself against a missing key or a string where it expects a
     * number.
     *
     * @param array<string, string|int|bool|null> $props
     *
     * @return array<string, mixed>
     */
    public function context(array $props, string $locale): array;

    /**
     * How long a rendered fragment may be reused, in seconds; null for content
     * that has to be computed on every request.
     *
     * This is what keeps a volatile block — a news list, a counter — from
     * forcing the whole page out of the cache (SPEC §3.5).
     */
    public function cacheTtl(): ?int;
}
