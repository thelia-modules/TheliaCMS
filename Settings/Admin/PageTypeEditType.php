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
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use TheliaCMS\TheliaCMS;

/**
 * A page type as a template: its name and description in the language being
 * edited, and the brief every page of the type is written from. The fields
 * are edited one by one beside it.
 */
final class PageTypeEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Name',
                'required' => false,
                'constraints' => [new Length(max: 255)],
                'help' => 'What the type is called in the back office and above a list of its pages, in this language.',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 2],
                'constraints' => [new Length(max: 2000)],
            ])
            ->add('briefText', TextareaType::class, [
                'label' => 'Brief of the pages of this type',
                'required' => false,
                'attr' => ['rows' => 6],
                'constraints' => [new Length(max: 20000)],
                'help' => 'What every page of this type is about. The generator reads it before the brief of the page; how the pages are written goes in the writing instructions.',
            ])
            ->add('briefTone', TextType::class, [
                'label' => 'Tone',
                'required' => false,
                'constraints' => [new Length(max: 200)],
            ])
            ->add('briefAudience', TextType::class, [
                'label' => 'Audience',
                'required' => false,
                'constraints' => [new Length(max: 200)],
            ])
            ->add('instructions', TextareaType::class, [
                'label' => 'Writing instructions',
                'required' => false,
                'attr' => ['rows' => 8, 'data-testid' => 'cms-page-type-instructions'],
                'constraints' => [new Length(max: 20000)],
                'help' => 'How every page of this template is written: tone, length, sections expected, mandatory mentions. In markdown. The generator applies them as rules to every page of the template; the brief says what the pages are about.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => TheliaCMS::DOMAIN_NAME]);
    }
}
