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

namespace TheliaCMS\Partial\Definition;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Menu\CmsMenuProvider;
use TheliaCMS\Model\CmsMenuQuery;
use TheliaCMS\Partial\PartialDefinitionInterface;
use TheliaCMS\Partial\PartialProp;
use TheliaCMS\TheliaCMS;

/**
 * A menu, placed inside a page rather than in the theme layout — a side menu on
 * a section landing page, a short list of links at the end of an article.
 */
final readonly class MenuPartial implements PartialDefinitionInterface
{
    public function __construct(
        private CmsMenuProvider $menus,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function name(): string
    {
        return 'cms-menu';
    }

    public function label(): string
    {
        return $this->trans('Menu');
    }

    public function themeTemplate(): string
    {
        return 'cms/partials/menu';
    }

    public function fallbackTemplate(): string
    {
        return '@TheliaCMSModule/front/partials/menu.html.twig';
    }

    public function props(): array
    {
        return [
            PartialProp::reference('menu', $this->trans('Menu'), source: $this->urls->generate('admin.cms.partials.sources.menus')),
            PartialProp::text('heading', $this->trans('Heading'), required: false, help: $this->trans('Shown above the menu. Leave empty for no heading.')),
        ];
    }

    public function context(array $props, string $locale): array
    {
        // Menus are addressed by code on the front office, but an editor picks
        // one from a list: the id is resolved to its code here, so renaming a
        // menu keeps the block working.
        $menu = CmsMenuQuery::create()->findPk((int) $props['menu']);

        return [
            'heading' => $props['heading'],
            'items' => null === $menu ? [] : $this->menus->menu((string) $menu->getCode(), $locale),
        ];
    }

    /**
     * The menu provider caches the tree itself, and drops it on every write that
     * can change it — caching the markup on top would only add a second
     * lifetime nothing invalidates.
     */
    public function cacheTtl(): ?int
    {
        return null;
    }

    private function trans(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
