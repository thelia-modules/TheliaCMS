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

use TheliaCMS\Form\Admin\CmsFormRepository;
use TheliaCMS\Generation\GenerationRequest;

/**
 * The forms of the site a page may embed with `Partial:cms-form`: a contact
 * page asks for one, and the model has to take its id from here rather than
 * guess it.
 */
final readonly class SearchFormsTool implements GeneratorToolInterface
{
    public function __construct(
        private CmsFormRepository $forms,
    ) {
    }

    public function name(): string
    {
        return 'search_forms';
    }

    public function description(): string
    {
        return 'Lists the forms of the site (contact, quote, newsletter…), with their id, code and title; an empty query lists them all. The id is what the `form` prop of `Partial:cms-form` takes; never use an id that did not come from here.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string', 'description' => 'A part of the title or of the code, or empty for every form.']],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function run(array $input, GenerationRequest $request): string
    {
        $query = mb_strtolower(trim((string) ($input['query'] ?? '')));
        $found = [];

        foreach ($this->forms->forms($request->locale) as $row) {
            $form = $row->form;
            $title = trim((string) $form->getTitle());
            $code = (string) $form->getCode();

            if ('' !== $query && !str_contains(mb_strtolower($title), $query) && !str_contains(mb_strtolower($code), $query)) {
                continue;
            }

            $found[] = ['id' => (int) $form->getId(), 'code' => $code, 'title' => '' === $title ? $code : $title, 'fields' => $row->fieldCount];
        }

        if ([] === $found) {
            return '' === $query ? 'The site has no form yet: leave the form out of the page.' : 'No form matches. Try an empty query to list them all, or leave the form out.';
        }

        return json_encode($found, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
