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

namespace TheliaCMS\ImportExport\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class SaveAsTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pageId', ChoiceType::class, [
                'label' => 'Page to keep',
                'choices' => $options['pages'],
                'placeholder' => 'Choose a page',
                'constraints' => [new NotBlank(message: 'Choose the page this template is made from.')],
            ])
            ->add('title', TextType::class, [
                'label' => 'Template name',
                'constraints' => [new NotBlank(message: 'Give the template a name.'), new Length(max: 255)],
                'help' => 'What an editor will read when starting a page: "Landing page", "Service sheet".',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'What it is for',
                'required' => false,
                'attr' => ['rows' => 2],
                'constraints' => [new Length(max: 500)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['translation_domain' => 'theliacms'])
            ->setRequired('pages')
            ->setAllowedTypes('pages', 'array');
    }
}
