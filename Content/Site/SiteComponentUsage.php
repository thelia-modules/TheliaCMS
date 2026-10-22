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

namespace TheliaCMS\Content\Site;

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Model\CmsItemContent;
use TheliaCMS\Model\CmsItemContentQuery;
use TheliaCMS\Model\CmsPageContent;
use TheliaCMS\Model\CmsPageContentQuery;
use TheliaCMS\Model\CmsPageI18nQuery;

/**
 * The pages, and the documents of the catalogue items, that hold a component
 * of the site, and what a new version of it would do to them.
 *
 * A page stores the props of its sections; a version of the component that
 * no longer takes them (a prop removed, retyped, made required) leaves the
 * page with a document the catalogue refuses. Such a version is not kept:
 * every section it would break is named, draft and published, in every
 * language, so the editor — or the model — knows what to keep.
 */
final readonly class SiteComponentUsage
{
    /** How many breakages are named; the rest is counted. */
    private const int NAMED = 15;

    /**
     * @return list<string> one sentence per section the version would break, none when it breaks nothing
     */
    public function breakages(SiteComponent $component, PropSchema $schema): array
    {
        $name = $component->name();
        $breakages = [];

        foreach ($this->contents($name) as $content) {
            foreach (['draft' => $content->getDraftDocument(), 'published' => $content->getPublishedDocument()] as $state => $json) {
                foreach (self::sectionsOf((string) $json, $name) as $id => $props) {
                    try {
                        $schema->normalize($props);
                    } catch (InvalidDocumentException $exception) {
                        $breakages[] = \sprintf(
                            'The page "%s" (#%d, %s, %s) holds the section "%s", which this version refuses: %s',
                            $this->titleOf((int) $content->getPageId(), (string) $content->getLocale()),
                            $content->getPageId(),
                            $content->getLocale(),
                            $state,
                            $id,
                            implode(' ', $exception->problems),
                        );
                    }
                }
            }
        }

        // The document of a catalogue item breaks the same way.
        foreach ($this->itemContents($name) as $content) {
            foreach (['draft' => $content->getDraftDocument(), 'published' => $content->getPublishedDocument()] as $state => $json) {
                foreach (self::sectionsOf((string) $json, $name) as $id => $props) {
                    try {
                        $schema->normalize($props);
                    } catch (InvalidDocumentException $exception) {
                        $breakages[] = \sprintf(
                            'The %s #%d (%s, %s) holds the section "%s", which this version refuses: %s',
                            $content->getItemType(),
                            $content->getItemId(),
                            $content->getLocale(),
                            $state,
                            $id,
                            implode(' ', $exception->problems),
                        );
                    }
                }
            }
        }

        if (\count($breakages) > self::NAMED) {
            $more = \count($breakages) - self::NAMED;
            $breakages = \array_slice($breakages, 0, self::NAMED);
            $breakages[] = \sprintf('And %d more section(s).', $more);
        }

        return $breakages;
    }

    /**
     * The pages and languages whose draft holds the component: the drafts to
     * render again once a new version is kept.
     *
     * @return list<array{pageId: int, locale: string}>
     */
    public function drafts(string $name): array
    {
        $drafts = [];

        foreach ($this->contents($name) as $content) {
            if ([] !== self::sectionsOf((string) $content->getDraftDocument(), $name)) {
                $drafts[] = ['pageId' => (int) $content->getPageId(), 'locale' => (string) $content->getLocale()];
            }
        }

        return $drafts;
    }

    /**
     * The contents of the pages out of the bin that may name the component:
     * narrowed by the database on the name, then read section by section.
     *
     * @return list<CmsPageContent>
     */
    private function contents(string $name): array
    {
        $like = '%'.addcslashes(json_encode($name, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES), '%_\\').'%';

        return iterator_to_array(
            CmsPageContentQuery::create()
                ->useCmsPageQuery()
                    ->filterByDeletedAt(null, Criteria::ISNULL)
                ->endUse()
                ->condition('draft', 'cms_page_content.draft_document LIKE ?', $like)
                ->condition('published', 'cms_page_content.published_document LIKE ?', $like)
                ->where(['draft', 'published'], Criteria::LOGICAL_OR)
                ->orderByPageId()
                ->orderByLocale()
                ->find(),
            false,
        );
    }

    /**
     * The documents of the catalogue items that may name the component.
     *
     * @return list<CmsItemContent>
     */
    private function itemContents(string $name): array
    {
        $like = '%'.addcslashes(json_encode($name, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES), '%_\\').'%';

        return iterator_to_array(
            CmsItemContentQuery::create()
                ->condition('draft', 'cms_item_content.draft_document LIKE ?', $like)
                ->condition('published', 'cms_item_content.published_document LIKE ?', $like)
                ->where(['draft', 'published'], Criteria::LOGICAL_OR)
                ->orderByItemType()
                ->orderByItemId()
                ->orderByLocale()
                ->find(),
            false,
        );
    }

    /**
     * The props of the sections of a stored document that use the component,
     * by section id; read from the JSON, so a document the catalogue would
     * refuse still says what it holds.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function sectionsOf(string $json, string $name): array
    {
        $decoded = '' === $json ? null : json_decode($json, true);
        $sections = [];

        foreach (\is_array($decoded) && \is_array($decoded['sections'] ?? null) ? $decoded['sections'] : [] as $rank => $section) {
            if (\is_array($section) && $name === ($section['component'] ?? null)) {
                $sections[\is_string($section['id'] ?? null) ? $section['id'] : (string) $rank] = \is_array($section['props'] ?? null) ? $section['props'] : [];
            }
        }

        return $sections;
    }

    private function titleOf(int $pageId, string $locale): string
    {
        return (string) CmsPageI18nQuery::create()->filterById($pageId)->filterByLocale($locale)->findOne()?->getTitle();
    }
}
