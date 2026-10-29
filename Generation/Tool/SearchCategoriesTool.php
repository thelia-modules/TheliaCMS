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

namespace TheliaCMS\Generation\Tool;

use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Partial\Catalogue\PickableCategories;
use TheliaCMS\Partial\Catalogue\PickableItem;

final readonly class SearchCategoriesTool implements GeneratorToolInterface
{
    public function __construct(
        private PickableCategories $categories,
    ) {
    }

    public function name(): string
    {
        return 'search_categories';
    }

    public function description(): string
    {
        return 'Finds the categories of the shop that are online, by a part of their name; each comes with its parent so two categories of the same name can be told apart. Use the id in a category section or reference.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string', 'description' => 'A part of the name of the category.']],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function run(array $input, GenerationRequest $request): string
    {
        $items = $this->categories->search(trim((string) ($input['query'] ?? '')), $request->locale);

        if ([] === $items) {
            return 'No category online matches.';
        }

        return json_encode(array_map(static fn (PickableItem $item): array => ['id' => $item->id, 'name' => $item->name], $items), \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
