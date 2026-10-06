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

namespace TheliaCMS\Builder;

use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\TheliaCMS;

/**
 * The blocks of the editor itself: what the page-builder bundle and the
 * GrapesJS presets register, in the three categories of the panel.
 *
 * Mirrored on the server because the settings screen lists them where the
 * editor is not running. The ids are the ones the editor registers the blocks
 * under (`pluginManager.js` of the bundle), so the same list can take them out
 * of the panel. A block the bundle adds later is offered to editors all the
 * same: this list only decides what the screen can switch off.
 */
final readonly class EditorBaseBlocks
{
    /**
     * Category key => [label of the category, id => label of the block], in
     * the order of the panel.
     */
    private const array GROUPS = [
        'basic' => ['Basic blocks', [
            'title' => 'Title',
            'text' => 'Text',
            'link' => 'Link',
            'image' => 'Image',
            'video' => 'Video',
            'map' => 'Map',
            'link-block' => 'Link block',
            'quote' => 'Quote',
            'text-basic' => 'Text section',
            'list' => 'List',
            'divider' => 'Divider',
            'icon' => 'Icon',
            'table-block' => 'Table',
        ]],
        'layout' => ['Layout blocks', [
            'section' => 'Section',
            'column1' => '1 column',
            'column2' => '2 columns',
            'column3' => '3 columns',
            'column3-7' => '2 columns 3/7',
        ]],
        'advanced' => ['Advanced blocks', [
            'accordion' => 'Accordion',
            'accordion-group' => 'Accordion group',
            'countdown' => 'Countdown',
            // Offered to the holders of the custom-code resource only; the
            // setting can still take it away from everyone.
            'custom-code' => 'HTML',
        ]],
    ];

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Read in the language of the back office, as the panel names them.
     *
     * @return array<string, array{label: string, blocks: array<string, string>}>
     */
    public function groups(): array
    {
        $groups = [];

        foreach (self::GROUPS as $key => [$label, $blocks]) {
            $groups[$key] = [
                'label' => $this->translator->trans($label, [], TheliaCMS::DOMAIN_NAME),
                'blocks' => array_map(fn (string $blockLabel): string => $this->translator->trans($blockLabel, [], TheliaCMS::DOMAIN_NAME), $blocks),
            ];
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_merge(...array_map(static fn (array $group): array => array_keys($group[1]), array_values(self::GROUPS)));
    }
}
