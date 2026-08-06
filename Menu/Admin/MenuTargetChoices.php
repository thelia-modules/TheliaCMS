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
use Thelia\Model\ContentQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use TheliaCMS\Page\Admin\CmsPageAdminRepository;

/**
 * What the menu entry form offers to point at.
 *
 * Contents and folders come from the core: a site running the CMS keeps using
 * them for its news, and its navigation has to be able to reach them.
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

        return $this->folderBranch($byParent, 0, 0, $locale);
    }

    /**
     * @param array<int, list<Folder>> $byParent
     *
     * @return array<string, int>
     */
    private function folderBranch(array $byParent, int $parent, int $depth, string $locale): array
    {
        $choices = [];

        foreach ($byParent[$parent] ?? [] as $folder) {
            $id = (int) $folder->getId();
            $choices[str_repeat('— ', $depth).$this->label($folder->setLocale($locale)->getTitle(), $id)] = $id;
            $choices += $this->folderBranch($byParent, $id, $depth + 1, $locale);
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
