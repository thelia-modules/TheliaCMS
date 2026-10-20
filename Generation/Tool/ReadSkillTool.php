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
use TheliaCMS\Skill\SkillRepository;

/**
 * The body of a skill the prompt only named: read when the page calls for
 * it, so the prompt carries the rules every page needs and no more.
 */
final readonly class ReadSkillTool implements GeneratorToolInterface
{
    public function __construct(
        private SkillRepository $skills,
    ) {
    }

    public function name(): string
    {
        return 'read_skill';
    }

    public function description(): string
    {
        return 'Reads one of the skills listed as available on demand, by its code.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['skill' => ['type' => 'string', 'description' => 'The code of the skill.']],
            'required' => ['skill'],
            'additionalProperties' => false,
        ];
    }

    public function run(array $input, GenerationRequest $request): string
    {
        $skill = $this->skills->find(trim((string) ($input['skill'] ?? '')));

        if (null === $skill || !$skill->enabled) {
            return 'There is no such skill.';
        }

        return $skill->body;
    }
}
