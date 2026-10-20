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

namespace TheliaCMS\Generation\Admin;

use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use TheliaCMS\Content\Prop;
use TheliaCMS\Content\PropType;
use TheliaCMS\Content\Section;
use TheliaCMS\Content\SectionDefinitionInterface;
use TheliaCMS\Media\CmsMediaLibrary;

/**
 * A form for the props of one section, read from the schema of its
 * component: an editor corrects a word without paying a generation.
 *
 * Every prop type has its field; a `html` prop is a textarea the Tiptap
 * module upgrades when it is there, and a textarea when it is not. What the
 * form gives back is props the way the model writes them, so the validator
 * reads them exactly as it reads a generated document.
 */
final readonly class SectionFormBuilder
{
    public const string PREFIX = 'cms_section_';

    public function __construct(
        private FormFactoryInterface $forms,
        private CmsMediaLibrary $library,
    ) {
    }

    public static function name(string $sectionId): string
    {
        return self::PREFIX.preg_replace('/[^a-z0-9_]/i', '_', $sectionId);
    }

    public function build(SectionDefinitionInterface $definition, Section $section, string $action): FormInterface
    {
        $builder = $this->forms->createNamedBuilder(self::name($section->id), FormType::class, $this->dataFor($definition->schema()->props, $section->props), [
            'action' => $action,
            'translation_domain' => 'theliacms',
            'csrf_protection' => false,
            'allow_extra_fields' => true,
        ]);

        $builder->add('section', HiddenType::class, ['data' => $section->id, 'mapped' => false]);
        $this->addProps($builder, $definition->schema()->props);

        return $builder->getForm();
    }

    /**
     * The props a submitted form holds, in the shape the schema reads.
     *
     * @param array<string, mixed> $data
     * @param list<Prop>           $props
     *
     * @return array<string, mixed>
     */
    public static function propsFrom(array $data, array $props): array
    {
        $values = [];

        foreach ($props as $prop) {
            $value = $data[$prop->name] ?? null;

            $values[$prop->name] = match ($prop->type) {
                // An empty choice is "none", which the schema spells 0.
                PropType::Image, PropType::Reference => (int) ($value ?? 0),
                PropType::Integer => (int) ($value ?? $prop->default ?? 0),
                PropType::Boolean => (bool) $value,
                PropType::Items => array_values(array_map(static fn (array $item): array => self::propsFrom($item, $prop->items), \is_array($value) ? $value : [])),
                PropType::Json => $value ?? [],
                default => (string) ($value ?? ''),
            };
        }

        return $values;
    }

    /**
     * @param list<Prop> $props
     */
    public function addProps(FormBuilderInterface $builder, array $props): void
    {
        foreach ($props as $prop) {
            $this->addProp($builder, $prop);
        }
    }

    private function addProp(FormBuilderInterface $builder, Prop $prop): void
    {
        $common = [
            'label' => $prop->label,
            'required' => $prop->required,
            'help' => $prop->help,
            // The labels and the help come translated from the catalogue.
            'translation_domain' => false,
        ];

        match ($prop->type) {
            PropType::Html => $builder->add($prop->name, TextareaType::class, $common + [
                'attr' => ['rows' => 8, 'class' => 'wysiwyg', 'data-cms-html' => ''],
            ]),
            PropType::Text => $builder->add($prop->name, TextType::class, $common + [
                'attr' => ['maxlength' => $prop->max ?? 255],
            ]),
            PropType::Url => $builder->add($prop->name, TextType::class, $common + [
                'attr' => ['maxlength' => $prop->max ?? 2048, 'placeholder' => '/contact'],
            ]),
            PropType::Integer => $builder->add($prop->name, IntegerType::class, $common + [
                'attr' => ['min' => $prop->min, 'max' => $prop->max],
            ]),
            PropType::Boolean => $builder->add($prop->name, CheckboxType::class, $common + ['required' => false]),
            PropType::Choice => $builder->add($prop->name, ChoiceType::class, $common + [
                'choices' => array_flip(array_map(strval(...), $prop->choices)),
                'choice_translation_domain' => false,
            ]),
            PropType::Image => $builder->add($prop->name, ChoiceType::class, $common + [
                'choices' => $this->imageChoices(),
                'placeholder' => $prop->required ? false : '—',
                'choice_translation_domain' => false,
            ]),
            PropType::Reference => $builder->add($prop->name, IntegerType::class, $common + [
                'attr' => array_filter([
                    'min' => 0,
                    'data-cms-picker' => $prop->picker,
                    'data-cms-picker-searchable' => $prop->searchable ? '1' : null,
                ]),
            ]),
            PropType::Items => $builder->add($prop->name, CollectionType::class, $common + [
                'entry_type' => SectionItemType::class,
                'entry_options' => ['props' => $prop->items, 'builder' => $this, 'label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'attr' => ['data-cms-items' => '', 'data-cms-items-min' => $prop->minItems ?? 0, 'data-cms-items-max' => $prop->maxItems ?? 12],
            ]),
            PropType::Json => $builder->add(
                $builder->create($prop->name, TextareaType::class, $common + ['attr' => ['rows' => 4, 'class' => 'font-monospace']])
                    ->addModelTransformer(new CallbackTransformer(
                        static fn (mixed $value): string => \is_string($value) ? $value : json_encode($value ?? [], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR),
                        static fn (?string $value): mixed => '' === trim((string) $value) ? [] : (json_decode((string) $value, true) ?? $value),
                    )),
            ),
        };
    }

    /**
     * The props as the form holds them: the ids of an image or a reference
     * are integers and an absent one is nothing, which the form reads as
     * "no choice".
     *
     * @param list<Prop>           $props
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function dataFor(array $props, array $values): array
    {
        $data = [];

        foreach ($props as $prop) {
            $value = $values[$prop->name] ?? $prop->default;

            $data[$prop->name] = match ($prop->type) {
                PropType::Image => null === $value || 0 === (int) $value ? null : (int) $value,
                PropType::Reference => null === $value ? null : (int) $value,
                PropType::Choice => null === $value ? null : (string) $value,
                PropType::Items => array_map(fn (array $item): array => $this->dataFor($prop->items, $item), \is_array($value) ? $value : []),
                default => $value,
            };
        }

        return $data;
    }

    /**
     * @return array<string, int>
     */
    private function imageChoices(): array
    {
        $locale = $this->library->locale();
        $choices = [];

        foreach ($this->library->images() as $image) {
            $title = trim((string) $image->setLocale($locale)->getTitle());
            $label = '' === $title ? (string) $image->getFileName() : $title;
            $choices[isset($choices[$label]) ? \sprintf('%s (#%d)', $label, $image->getId()) : $label] = (int) $image->getId();
        }

        return $choices;
    }
}
