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

namespace TheliaCMS\Page\Field;

use TheliaCMS\Model\CmsPage;
use TheliaCMS\Page\PageTypeRepository;

/**
 * The values of the fields of a page: the ones written once in
 * `cms_page.fields`, the ones written for each language in
 * `cms_page_i18n.translated_fields`, both JSON objects by field code.
 *
 * Values are read through the definition of the type of the page, never as
 * stored: a field the type no longer defines is not read, a value its field
 * no longer accepts reads as none. Writing drops both.
 */
final readonly class PageFields
{
    public function __construct(
        private PageTypeRepository $types,
    ) {
    }

    public function definitionsOf(CmsPage $page): FieldDefinitions
    {
        return $this->types->fieldsOf((string) $page->getPageType());
    }

    /**
     * Every field the type defines, by code, with its value in this language
     * or null.
     *
     * @return array<string, int|float|string|bool|null>
     */
    public function valuesOf(CmsPage $page, string $locale, ?FieldDefinitions $definitions = null): array
    {
        $definitions ??= $this->definitionsOf($page);

        if ($definitions->isEmpty()) {
            return [];
        }

        $page->setLocale($locale);
        $stored = [
            ...self::decode($page->getFields()),
            ...self::decode($page->getTranslatedFields()),
        ];
        $values = [];

        foreach ($definitions as $field) {
            try {
                $values[$field->code] = $field->normalize($stored[$field->code] ?? null);
            } catch (\InvalidArgumentException) {
                $values[$field->code] = null;
            }
        }

        return $values;
    }

    /**
     * Sets the values given on the page, in this language for the translated
     * fields, without saving it. A code left out keeps its value, a null
     * empties it; a code the type does not define is dropped, here and in
     * what the page held.
     *
     * @param array<mixed> $values
     *
     * @throws InvalidFieldsException when a value is not one its field takes
     */
    public function write(CmsPage $page, string $locale, array $values, ?FieldDefinitions $definitions = null): void
    {
        $definitions ??= $this->definitionsOf($page);
        $normalized = $definitions->normalize($values, $locale);
        $current = $this->valuesOf($page, $locale, $definitions);
        $merged = [...$current, ...$normalized];

        $page->setLocale($locale);
        $page->setFields(self::encode(self::only($merged, $definitions->filter(translated: false))));
        $page->setTranslatedFields(self::encode(self::only($merged, $definitions->filter(translated: true))));
    }

    /**
     * The values of these fields, empty ones left out.
     *
     * @param array<string, int|float|string|bool|null> $values
     *
     * @return array<string, int|float|string|bool>
     */
    private static function only(array $values, FieldDefinitions $definitions): array
    {
        $kept = [];

        foreach ($definitions as $field) {
            if (null !== ($values[$field->code] ?? null)) {
                $kept[$field->code] = $values[$field->code];
            }
        }

        return $kept;
    }

    /**
     * @param array<string, int|float|string|bool> $values
     */
    private static function encode(array $values): ?string
    {
        // A float written by json_encode loses nothing with this: 1.5 stays
        // 1.5, 2.0 is written 2.0 and read back as a float.
        return [] === $values ? null : json_encode($values, \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public static function decode(?string $json): array
    {
        if (null === $json || '' === trim($json)) {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
    }
}
