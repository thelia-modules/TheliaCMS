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

namespace TheliaCMS\Menu\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\ProductCategoryQuery;
use Thelia\Model\ProductQuery;
use TheliaCMS\Page\Admin\CmsPageAdminRepository;

/**
 * What the menu entry form offers to point at.
 *
 * Contents and folders come from the core: a site running the CMS keeps using
 * them for its news, and its navigation has to be able to reach them. So do
 * the categories and products of the catalogue, which a shop's main menu is
 * mostly made of.
 */
final readonly class MenuTargetChoices
{
    public function __construct(
        private CmsPageAdminRepository $pages,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function pages(string $locale): array
    {
        return $this->pages->parentChoices($locale);
    }

    /**
     * @return array<string, int>
     */
    public function contents(string $locale): array
    {
        $contents = ContentQuery::create()
            ->joinWithI18n($locale, Criteria::LEFT_JOIN)
            ->find();

        $choices = [];

        foreach ($contents as $content) {
            $choices[$this->label($content->setLocale($locale)->getTitle(), (int) $content->getId())] = (int) $content->getId();
        }

        // Contents are positioned inside their folder, which sorts a flat list
        // of every content into no order at all. Alphabetical is what someone
        // scanning a select expects.
        ksort($choices, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $choices;
    }

    /**
     * @return array<string, int>
     */
    public function folders(string $locale): array
    {
        $folders = FolderQuery::create()
            ->joinWithI18n($locale, Criteria::LEFT_JOIN)
            ->orderByParent()
            ->orderByPosition()
            ->find();

        /** @var array<int, list<Folder>> $byParent */
        $byParent = [];

        foreach ($folders as $folder) {
            $byParent[(int) $folder->getParent()][] = $folder;
        }

        return $this->branch($byParent, 0, 0, $locale);
    }

    /**
     * @return array<string, int> the tree of the categories, indented
     */
    public function categories(string $locale): array
    {
        return $this->branch($this->categoriesByParent($locale), 0, 0, $locale);
    }

    /**
     * A catalogue soon holds hundreds of products: a flat select of them is
     * unusable. They are grouped by their default category, in the order of
     * the category tree, each group named by its path so that two categories
     * sharing a name stay apart; the browser finds a product as it is typed.
     *
     * @return array<string, array<string, int>> products by category path
     */
    public function products(string $locale): array
    {
        $paths = $this->categoryPaths($this->categoriesByParent($locale), 0, [], $locale);
        $defaultCategories = [];

        foreach (ProductCategoryQuery::create()->filterByDefaultCategory(1)->find() as $link) {
            $defaultCategories[(int) $link->getProductId()] = (int) $link->getCategoryId();
        }

        $products = ProductQuery::create()
            ->joinWithI18n($locale, Criteria::LEFT_JOIN)
            ->find();

        $byCategory = array_fill_keys(array_keys($paths), []);
        $uncategorised = [];

        foreach ($products as $product) {
            $id = (int) $product->getId();
            $categoryId = $defaultCategories[$id] ?? null;
            $label = $this->label($product->setLocale($locale)->getTitle(), $id);

            if (null !== $categoryId && isset($byCategory[$categoryId])) {
                $byCategory[$categoryId] = $this->withUniqueLabel($byCategory[$categoryId], $label, $id);
            } else {
                $uncategorised = $this->withUniqueLabel($uncategorised, $label, $id);
            }
        }

        $choices = [];

        foreach ($byCategory as $categoryId => $group) {
            if ([] !== $group) {
                ksort($group, \SORT_NATURAL | \SORT_FLAG_CASE);
                $choices[$paths[$categoryId]] = $group;
            }
        }

        if ([] !== $uncategorised) {
            ksort($uncategorised, \SORT_NATURAL | \SORT_FLAG_CASE);
            $choices['No category'] = $uncategorised;
        }

        return $choices;
    }

    /**
     * @return array<int, list<Category>>
     */
    private function categoriesByParent(string $locale): array
    {
        $categories = CategoryQuery::create()
            ->joinWithI18n($locale, Criteria::LEFT_JOIN)
            ->orderByParent()
            ->orderByPosition()
            ->find();

        $byParent = [];

        foreach ($categories as $category) {
            $byParent[(int) $category->getParent()][] = $category;
        }

        return $byParent;
    }

    /**
     * @param array<int, list<Category>> $byParent
     * @param list<string>               $ancestors
     *
     * @return array<int, string> the path of each category, by id, in tree order
     */
    private function categoryPaths(array $byParent, int $parent, array $ancestors, string $locale): array
    {
        $paths = [];

        foreach ($byParent[$parent] ?? [] as $category) {
            $id = (int) $category->getId();
            $path = [...$ancestors, $this->label($category->setLocale($locale)->getTitle(), $id)];
            $paths[$id] = implode(' › ', $path);
            $paths += $this->categoryPaths($byParent, $id, $path, $locale);
        }

        return $paths;
    }

    /**
     * Two products of a group may share a title: the second one is told apart
     * by its identifier rather than silently replacing the first.
     *
     * @param array<string, int> $group
     *
     * @return array<string, int>
     */
    private function withUniqueLabel(array $group, string $label, int $id): array
    {
        $group[isset($group[$label]) ? $label.' (#'.$id.')' : $label] = $id;

        return $group;
    }

    /**
     * @param array<int, list<Category|Folder>> $byParent
     *
     * @return array<string, int>
     */
    private function branch(array $byParent, int $parent, int $depth, string $locale): array
    {
        $choices = [];

        foreach ($byParent[$parent] ?? [] as $node) {
            $id = (int) $node->getId();
            $choices[str_repeat('— ', $depth).$this->label($node->setLocale($locale)->getTitle(), $id)] = $id;
            $choices += $this->branch($byParent, $id, $depth + 1, $locale);
        }

        return $choices;
    }

    private function label(?string $title, int $id): string
    {
        $title = trim((string) $title);

        // An untranslated row still has to be selectable, and by something
        // stable: its identifier.
        return '' === $title ? '#'.$id : $title;
    }
}
