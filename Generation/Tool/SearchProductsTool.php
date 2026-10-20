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
use TheliaCMS\Partial\Catalogue\PickableItem;
use TheliaCMS\Partial\Catalogue\PickableProducts;

final readonly class SearchProductsTool implements GeneratorToolInterface
{
    public function __construct(
        private PickableProducts $products,
    ) {
    }

    public function name(): string
    {
        return 'search_products';
    }

    public function description(): string
    {
        return 'Finds the products of the shop that are online, by the start of their reference, a part of their name or their barcode. Use the id in a product section or a product reference.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string', 'description' => 'A part of the name, the start of the reference, or the barcode.']],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function run(array $input, GenerationRequest $request): string
    {
        $items = $this->products->search(trim((string) ($input['query'] ?? '')), $request->locale);

        if ([] === $items) {
            return 'No product online matches.';
        }

        return json_encode(array_map(static fn (PickableItem $item): array => ['id' => $item->id, 'name' => $item->name], $items), \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
