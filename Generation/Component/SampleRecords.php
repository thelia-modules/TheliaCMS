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

namespace TheliaCMS\Generation\Component;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Folder;
use Thelia\Model\FolderQuery;
use Thelia\Model\Lang;
use Thelia\Model\Product;
use Thelia\Model\ProductQuery;
use TheliaCMS\Content\PickedRecord;
use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropSchema;
use TheliaCMS\Content\PropType;
use TheliaCMS\Form\Admin\CmsFormRepository;
use TheliaCMS\Menu\Admin\CmsMenuRepository;
use TheliaCMS\Model\CmsForm;
use TheliaCMS\Model\CmsMenu;

/**
 * The records of the shop a `reference` of the sample of a component may
 * name: a few of each kind an editor picks from, so the example shows real
 * products rather than an empty band, and the message stays short. The
 * products, categories and folders shown on the site, the most recent first;
 * the forms not deleted and the shared menus, by code.
 */
final readonly class SampleRecords
{
    public const array LIMITS = [
        PickedRecord::Product->value => 8,
        PickedRecord::Category->value => 4,
        PickedRecord::Folder->value => 4,
        PickedRecord::Form->value => 3,
        PickedRecord::Menu->value => 3,
    ];

    public function __construct(
        private CmsFormRepository $forms,
        private CmsMenuRepository $menus,
    ) {
    }

    /**
     * Products, categories, folders, forms, then menus.
     *
     * @return list<SampleRecord>
     */
    public function offered(string $locale): array
    {
        $languages = array_values(array_unique([$locale, Lang::getDefaultLanguage()->getLocale()]));
        $records = [];

        foreach (PickedRecord::cases() as $record) {
            foreach ($this->find($record) as $model) {
                $name = $this->nameOf($model, $languages);

                if ($model instanceof Product) {
                    $name = '' === $name ? (string) $model->getRef() : \sprintf('%s (%s)', $name, $model->getRef());
                } elseif ('' === $name && ($model instanceof CmsForm || $model instanceof CmsMenu)) {
                    $name = (string) $model->getCode();
                }

                $records[] = new SampleRecord($record, (int) $model->getId(), $name);
            }
        }

        return $records;
    }

    /**
     * What the sample names that the shop does not show: an id the model made
     * up draws an empty band, so the component goes back for correction.
     *
     * @param array<string, mixed> $sample normalised by its props
     *
     * @return list<string>
     */
    public function unknown(PropSchema $schema, array $sample): array
    {
        /** @var array<string, array<string, int>> $named where => id, by kind of record */
        $named = [];

        foreach ($schema->props as $prop) {
            if (PropType::Reference === $prop->type && \is_int($sample[$prop->name] ?? null)) {
                $named[(string) $prop->record][\sprintf('"%s"', $prop->name)] = $sample[$prop->name];
            }

            if (PropType::Items !== $prop->type || !\is_array($sample[$prop->name] ?? null)) {
                continue;
            }

            foreach (array_values($sample[$prop->name]) as $index => $row) {
                foreach (array_filter($prop->items, static fn (Prop $item): bool => PropType::Reference === $item->type) as $item) {
                    $value = \is_array($row) ? ($row[$item->name] ?? null) : null;

                    if (\is_int($value) && $value > 0) {
                        $named[(string) $item->record][\sprintf('"%s" of row %d of "%s"', $item->name, $index + 1, $prop->name)] = $value;
                    }
                }
            }
        }

        $problems = [];

        foreach ($named as $kind => $ids) {
            $record = PickedRecord::tryFrom($kind);
            $shown = null === $record ? [] : $this->shown($record, array_values(array_unique($ids)));

            foreach ($ids as $where => $id) {
                if (!\in_array($id, $shown, true)) {
                    $problems[] = \sprintf('The sample: %s names %s %d, which the shop does not show. Take the id of one the message lists under "Records of the shop for the sample".', $where, $kind, $id);
                }
            }
        }

        return $problems;
    }

    /**
     * @return iterable<Product|Category|Folder|CmsForm|CmsMenu>
     */
    private function find(PickedRecord $record): iterable
    {
        $limit = self::LIMITS[$record->value];

        return match ($record) {
            PickedRecord::Product, PickedRecord::Category, PickedRecord::Folder => $this->visible($record)->orderByCreatedAt(Criteria::DESC)->orderById(Criteria::DESC)->limit($limit)->find(),
            PickedRecord::Form => \array_slice($this->forms->liveForms(), 0, $limit),
            PickedRecord::Menu => \array_slice($this->menus->sharedMenus(), 0, $limit),
        };
    }

    /**
     * Which of the ids name a record the list could have offered.
     *
     * @param list<int> $ids
     *
     * @return list<int>
     */
    private function shown(PickedRecord $record, array $ids): array
    {
        $found = match ($record) {
            PickedRecord::Product, PickedRecord::Category, PickedRecord::Folder => $this->visible($record)->filterById($ids, Criteria::IN)->select('Id')->find()->toArray(),
            PickedRecord::Form => array_map(static fn (CmsForm $form): ?int => $form->getId(), $this->forms->liveForms()),
            PickedRecord::Menu => array_map(static fn (CmsMenu $menu): ?int => $menu->getId(), $this->menus->sharedMenus()),
        };

        return array_map(intval(...), $found);
    }

    private function visible(PickedRecord $record): ProductQuery|CategoryQuery|FolderQuery
    {
        return match ($record) {
            PickedRecord::Product => ProductQuery::create()->filterByVisible(1),
            PickedRecord::Category => CategoryQuery::create()->filterByVisible(1),
            default => FolderQuery::create()->filterByVisible(1),
        };
    }

    /**
     * The title in the language of the sample, then in the default language
     * of the shop.
     *
     * @param list<string> $languages
     */
    private function nameOf(Product|Category|Folder|CmsForm|CmsMenu $model, array $languages): string
    {
        foreach ($languages as $language) {
            $model->setLocale($language);
            $title = trim((string) $model->getTitle());

            if ('' !== $title) {
                return $title;
            }
        }

        return '';
    }
}
