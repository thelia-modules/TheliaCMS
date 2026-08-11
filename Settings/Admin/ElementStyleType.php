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

namespace TheliaCMS\Settings\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use TheliaCMS\Settings\FontStacks;
use TheliaCMS\Settings\SiteStylesCss;

/**
 * The style fields of one element, as {@see \TheliaCMS\Settings\ElementStyle}
 * stores them.
 *
 * Everything is optional and everything submitted goes through the value
 * object, which drops what does not have the shape of its property: this form
 * lays fields out and offers choices, it does not stand guard.
 */
final class ElementStyleType extends AbstractType
{
    /** Which fields each kind of element shows. */
    private const array FIELDS = [
        'text' => ['font', 'size', 'weight', 'lineHeight', 'letterSpacing', 'transform', 'color'],
        'link' => ['color', 'hoverColor', 'underline', 'weight'],
        'button' => ['font', 'size', 'weight', 'letterSpacing', 'transform', 'color', 'background', 'radius'],
    ];

    public function __construct(
        private readonly FontStacks $stacks,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $fields = self::FIELDS[$options['variant']];

        if (\in_array('font', $fields, true)) {
            $builder->add('font', ChoiceType::class, [
                'label' => 'Font',
                'required' => false,
                'placeholder' => 'Theme default',
                'choices' => $this->fontChoices($options['fonts']),
            ]);
        }

        if (\in_array('size', $fields, true)) {
            $builder->add('size', TextType::class, [
                'label' => 'Size',
                'required' => false,
                'attr' => ['placeholder' => '1.25rem'],
            ]);
        }

        if (\in_array('weight', $fields, true)) {
            $builder->add('weight', ChoiceType::class, [
                'label' => 'Font weight',
                'required' => false,
                'placeholder' => 'Theme default',
                'choices' => [
                    'Thin 100' => '100', 'Extra-light 200' => '200', 'Light 300' => '300',
                    'Regular 400' => '400', 'Medium 500' => '500', 'Semi-bold 600' => '600',
                    'Bold 700' => '700', 'Extra-bold 800' => '800', 'Black 900' => '900',
                ],
            ]);
        }

        if (\in_array('lineHeight', $fields, true)) {
            $builder->add('lineHeight', TextType::class, [
                'label' => 'Line height',
                'required' => false,
                'attr' => ['placeholder' => '1.5'],
            ]);
        }

        if (\in_array('letterSpacing', $fields, true)) {
            $builder->add('letterSpacing', TextType::class, [
                'label' => 'Letter spacing',
                'required' => false,
                'attr' => ['placeholder' => '0.02em'],
            ]);
        }

        if (\in_array('transform', $fields, true)) {
            $builder->add('transform', ChoiceType::class, [
                'label' => 'Case',
                'required' => false,
                'placeholder' => 'As written',
                'choices' => [
                    'UPPERCASE' => 'uppercase',
                    'lowercase' => 'lowercase',
                    'Capitalized' => 'capitalize',
                    'As written, always' => 'none',
                ],
            ]);
        }

        if (\in_array('color', $fields, true)) {
            $builder->add('color', TextType::class, [
                'label' => 'Colour',
                'required' => false,
                'attr' => ['placeholder' => '#111827', 'pattern' => '#[0-9a-fA-F]{3,8}'],
            ]);
        }

        if (\in_array('hoverColor', $fields, true)) {
            $builder->add('hoverColor', TextType::class, [
                'label' => 'Colour under the pointer',
                'required' => false,
                'attr' => ['placeholder' => '#1d4ed8', 'pattern' => '#[0-9a-fA-F]{3,8}'],
            ]);
        }

        if (\in_array('underline', $fields, true)) {
            $builder->add('underline', ChoiceType::class, [
                'label' => 'Underline',
                'required' => false,
                'placeholder' => 'Theme default',
                'choices' => [
                    'Underlined' => 'underline',
                    'Not underlined' => 'none',
                ],
            ]);
        }

        if (\in_array('background', $fields, true)) {
            $builder->add('background', TextType::class, [
                'label' => 'Background',
                'required' => false,
                'attr' => ['placeholder' => '#1d4ed8', 'pattern' => '#[0-9a-fA-F]{3,8}'],
            ]);
        }

        if (\in_array('radius', $fields, true)) {
            $builder->add('radius', TextType::class, [
                'label' => 'Corner radius',
                'required' => false,
                'attr' => ['placeholder' => '6px'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'translation_domain' => 'theliacms',
                'variant' => 'text',
                'fonts' => [],
            ])
            ->setAllowedValues('variant', array_keys(self::FIELDS))
            ->setAllowedTypes('fonts', 'array');
    }

    /**
     * The stacks of installed faces first, then what this site uploaded.
     *
     * @param list<string> $fonts
     *
     * @return array<string, array<string, string>|string>
     */
    private function fontChoices(array $fonts): array
    {
        $stacks = [];

        foreach ($this->stacks->labels() as $key => $label) {
            $stacks[$label] = 'stack:'.$key;
        }

        if ([] === $fonts) {
            return $stacks;
        }

        $uploaded = [];

        foreach ($fonts as $file) {
            $uploaded[SiteStylesCss::familyOf($file)] = 'file:'.$file;
        }

        return [
            'Installed fonts' => $stacks,
            'Uploaded fonts' => $uploaded,
        ];
    }
}
