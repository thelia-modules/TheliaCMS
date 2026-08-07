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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class PageFromTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title of the new page',
                'constraints' => [new NotBlank(message: 'The page needs a title.'), new Length(max: 255)],
            ])
            ->add('parentId', ChoiceType::class, [
                'label' => 'Under',
                'required' => false,
                'choices' => $options['parents'],
                'placeholder' => 'At the root of the site',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults(['translation_domain' => 'theliacms'])
            ->setRequired('parents')
            ->setAllowedTypes('parents', 'array');
    }
}
