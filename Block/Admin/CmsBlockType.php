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

namespace TheliaCMS\Block\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class CmsBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Name',
                'help' => 'What this block is called when you pick it in a page.',
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code',
                'help' => 'An identifier of your own, for example call-to-action. Letters, digits, dashes.',
                'constraints' => [
                    new NotBlank(),
                    new Length(max: 50),
                    new Regex(pattern: '/^[a-z0-9][a-z0-9-]*$/', message: 'Use lowercase letters, digits and dashes only.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'theliacms']);
    }
}
