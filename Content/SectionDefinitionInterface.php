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

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A component a section of a document may be made of.
 *
 * Implementing the interface is what registers it: the catalogue is built from
 * every tagged service, and from the providers that bring several at once. A
 * definition says what it is called, what it is for, what props it takes, and
 * renders them.
 */
#[AutoconfigureTag(SectionCatalog::TAG)]
interface SectionDefinitionInterface
{
    /**
     * The name a document refers to the component by, stable across versions:
     * `cms:richtext`, `Cms:Hero`, `Partial:cms-form`.
     */
    public function name(): string;

    /**
     * What a person reads in a list of components, translated.
     */
    public function label(): string;

    /**
     * One or two sentences on what the component is for and when to use it:
     * what a generator reads to pick it.
     */
    public function description(): string;

    public function schema(): PropSchema;

    /**
     * Props that show the component at its best: what the toolkit renders,
     * and the example a generator is shown.
     *
     * @return array<string, mixed>
     */
    public function sample(): array;

    /**
     * The props as they will be stored: unknown ones dropped, missing ones
     * given their default, wrong ones refused.
     *
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     *
     * @throws InvalidDocumentException when the props cannot be made sense of
     */
    public function normalizeProps(array $props): array;

    /**
     * The HTML of the section, from props already normalized.
     *
     * @param array<string, mixed> $props
     */
    public function render(array $props, string $locale): string;
}
