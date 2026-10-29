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

namespace TheliaCMS\Skill\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\TheliaCMS;

/**
 * What the site may change about a skill: its text, whether the generator
 * reads it, and where it comes in the order of reading.
 *
 * The message of the constraint is translated here: the validator looks it
 * up in the `validators` domain, where the module has no catalogue.
 */
final class CmsSkillType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('body', TextareaType::class, [
                'label' => 'Instructions',
                'attr' => ['rows' => 24, 'spellcheck' => 'false'],
                'constraints' => [new NotBlank(message: $this->translator->trans('The skill needs a text.', [], TheliaCMS::DOMAIN_NAME))],
                'help' => 'Read by the generator before it writes a page. Markdown, in the language you want the model to think in.',
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Read by the generator',
                'required' => false,
            ])
            ->add('position', IntegerType::class, [
                'label' => 'Position',
                'attr' => ['min' => 0],
                'help' => 'Skills are read in this order, lowest first.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => TheliaCMS::DOMAIN_NAME]);
    }
}
