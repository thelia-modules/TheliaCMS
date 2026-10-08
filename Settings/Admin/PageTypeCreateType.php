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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Page\PageTypeCode;
use TheliaCMS\TheliaCMS;

/**
 * The messages of the constraints are translated here: the validator looks
 * them up in the `validators` domain, where the module has no catalogue.
 */
final class PageTypeCreateType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'Code',
            'constraints' => [
                new NotBlank(message: $this->translate('Give the type a code.')),
                new Length(max: PageTypeCode::MAX_LENGTH),
                new Regex(
                    pattern: PageTypeCode::PATTERN,
                    message: $this->translate('Use lowercase letters, digits and single hyphens only, such as "recipe" or "news-item".'),
                ),
            ],
            'help' => 'Pages of this type are displayed with the cmspage-{code}.html.twig template of the theme.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => TheliaCMS::DOMAIN_NAME]);
    }

    private function translate(string $message): string
    {
        return $this->translator->trans($message, [], TheliaCMS::DOMAIN_NAME);
    }
}
