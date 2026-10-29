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

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TheliaCMS\Generation\GenerationRequest;

/**
 * A question the model may ask the site while it writes: what products
 * match, what pages exist, what pictures the library holds. Implementing the
 * interface is what offers the tool.
 */
#[AutoconfigureTag(GeneratorTools::TAG)]
interface GeneratorToolInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * The JSON schema of the input, an object with `additionalProperties`
     * false: the tool is declared strict.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * The answer, as text the model reads. JSON for a list, a sentence for
     * nothing found: never an exception, which would end the call.
     *
     * @param array<string, mixed> $input
     */
    public function run(array $input, GenerationRequest $request): string;
}
