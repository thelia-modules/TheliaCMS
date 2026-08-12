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
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;
use TheliaCMS\Settings\SiteTypography;

/**
 * The global styles screen: one section per element, the palette, and the
 * fonts of the site.
 */
final class SiteStylesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach (SiteTypography::ELEMENTS as $element) {
            $builder->add($element, ElementStyleType::class, [
                'label' => false,
                'variant' => match ($element) {
                    'a' => 'link',
                    'button' => 'button',
                    default => 'text',
                },
                'fonts' => $options['fonts'],
            ]);
        }

        $builder
            ->add('palette', TextareaType::class, [
                'label' => 'Colours of the site',
                'required' => false,
                'attr' => ['rows' => 6, 'placeholder' => "#111827\n#1d4ed8\n#ffffff"],
                'help' => 'One colour per line, as #rrggbb. These are the colours every picker of the editor offers first.',
            ])
            ->add('fontUploads', FileType::class, [
                'label' => 'Add fonts',
                'required' => false,
                'multiple' => true,
                'mapped' => false,
                'help' => 'WOFF2 files only — the format every current browser reads. One file per weight you need.',
                'attr' => ['accept' => '.woff2'],
                'constraints' => [
                    new All([
                        new File(maxSize: '2M'),
                    ]),
                ],
            ]);

        if ([] !== $options['fonts']) {
            $builder->add('fontDeletions', ChoiceType::class, [
                'label' => 'Delete fonts',
                'required' => false,
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
                'choices' => array_combine($options['fonts'], $options['fonts']),
                'help' => 'A deleted font stops loading everywhere it was chosen; the fallback faces carry the text.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'translation_domain' => 'theliacms',
                'fonts' => [],
            ])
            ->setAllowedTypes('fonts', 'array');
    }
}
