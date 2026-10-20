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

namespace TheliaCMS\Content\Section;

use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Content\PropType;
use TheliaCMS\Content\SectionDefinitionInterface;
use TheliaCMS\Front\ThemeTemplateRenderer;
use TheliaCMS\TheliaCMS;

/**
 * A section of the module, rendered by a template the theme may take over.
 *
 * The module ships `templates/front/sections/<slug>.html.twig`; a theme that
 * wants the section its own way ships `cms/sections/<slug>.html.twig`, the
 * same rule as the partials. The markup carries the `cms-*` class names the
 * block stylesheet of the module knows, so a section reads as a section on a
 * theme that knows nothing about the CMS.
 *
 * The template receives `props` as the schema normalised them, plus `images`:
 * each image prop resolved to its address and dimensions, by prop name, with
 * the items of a list resolved under the index of the item.
 */
abstract readonly class AbstractTemplateSection implements SectionDefinitionInterface
{
    public function __construct(
        protected ThemeTemplateRenderer $templates,
        protected TranslatorInterface $translator,
        protected SectionImages $images,
    ) {
    }

    /**
     * The last segment of the template name: `hero` for `Cms:Hero`.
     */
    abstract protected function slug(): string;

    /**
     * The name of the story of this section in the toolkit, same as the
     * template.
     */
    public function storySlug(): string
    {
        return $this->slug();
    }

    public function normalizeProps(array $props): array
    {
        return $this->schema()->normalize($props);
    }

    public function render(array $props, string $locale): string
    {
        return $this->templates->render(
            'cms/sections/'.$this->slug(),
            '@TheliaCMSModule/front/sections/'.$this->slug().'.html.twig',
            $this->context($props, $locale),
        );
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    protected function context(array $props, string $locale): array
    {
        return [
            'section' => $this->name(),
            'locale' => $locale,
            'props' => $props,
            'images' => $this->resolvedImages($this->schema(), $props),
            // Stable for the same props, so the ids the aria attributes point
            // at do not change between two renderings of the same page, and
            // two sections of the same kind on one page do not share them.
            'uid' => substr(sha1($this->name().json_encode($props, \JSON_THROW_ON_ERROR)), 0, 8),
        ];
    }

    protected function trans(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }

    /**
     * @param array<string, mixed> $props
     *
     * @return array<string, mixed>
     */
    private function resolvedImages(PropSchema $schema, array $props): array
    {
        $images = [];

        foreach ($schema->props as $prop) {
            if (PropType::Image === $prop->type) {
                $images[$prop->name] = $this->images->resolve($props[$prop->name] ?? null);
            }

            if (PropType::Items === $prop->type) {
                $itemSchema = new PropSchema($prop->items);

                foreach ($props[$prop->name] ?? [] as $index => $item) {
                    $images[$prop->name][$index] = $this->resolvedImages($itemSchema, \is_array($item) ? $item : []);
                }
            }
        }

        return $images;
    }

    /**
     * The props every section with a heading shares: the heading itself, and
     * the level it is written at.
     */
    protected function headingProp(string $label, bool $required = true): Prop
    {
        return Prop::text('title', $label, required: $required, max: 120);
    }
}
