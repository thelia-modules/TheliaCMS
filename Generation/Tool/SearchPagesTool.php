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

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Model\CmsPage;
use TheliaCMS\Model\CmsPageQuery;
use TheliaCMS\Page\PublishedPageRepository;

/**
 * The pages of the site a page may link to: the ones online in the language
 * of the page being written, by a part of their title. The page being written
 * is left out: a page does not link to itself.
 */
final readonly class SearchPagesTool implements GeneratorToolInterface
{
    private const int LIMIT = 20;

    public function __construct(
        private PublishedPageRepository $published,
    ) {
    }

    public function name(): string
    {
        return 'search_pages';
    }

    public function description(): string
    {
        return 'Finds the pages of the site that are online in the language of the page, by a part of their title; an empty query lists them all. Use the url for an internal link, the id for a page reference.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['query' => ['type' => 'string', 'description' => 'A part of the title, or empty for every page.']],
            'required' => ['query'],
            'additionalProperties' => false,
        ];
    }

    public function run(array $input, GenerationRequest $request): string
    {
        $query = trim((string) ($input['query'] ?? ''));
        $locale = $request->locale;

        $pages = CmsPageQuery::create()
            ->filterByDeletedAt(null, Criteria::ISNULL)
            ->filterById($request->page->getId(), Criteria::NOT_EQUAL)
            ->useCmsPageI18nQuery()
                ->filterByLocale($locale)
                ->_if('' !== $query)
                    ->filterByTitle('%'.addcslashes($query, '%_\\').'%', Criteria::LIKE)
                ->_endif()
            ->endUse()
            ->orderById()
            ->find();

        $found = [];

        foreach ($pages as $page) {
            \assert($page instanceof CmsPage);

            if (!$this->published->isReachable((int) $page->getId(), $locale)) {
                continue;
            }

            $page->setLocale($locale);
            $found[] = ['id' => (int) $page->getId(), 'title' => (string) $page->getTitle(), 'url' => $page->getUrl($locale)];

            if (\count($found) >= self::LIMIT) {
                break;
            }
        }

        if ([] === $found) {
            return '' === $query ? 'The site has no other page online in this language yet.' : 'No page online matches.';
        }

        return json_encode($found, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
