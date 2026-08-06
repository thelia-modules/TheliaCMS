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

namespace TheliaCMS\Page\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use TheliaCMS\Page\PageLayout;

final class CmsPageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Title',
                'constraints' => [new NotBlank(), new Length(max: 255)],
            ])
            ->add('slug', TextType::class, [
                'label' => 'URL slug',
                'required' => false,
                'help' => 'Leave empty to derive it from the title. Parent pages prefix it automatically.',
            ])
            ->add('parent', ChoiceType::class, [
                'label' => 'Parent page',
                'choices' => ['None (top level)' => 0] + $options['parent_choices'],
            ])
            ->add('layout', ChoiceType::class, [
                'label' => 'Layout',
                'choices' => [
                    'Default' => PageLayout::Default->value,
                    'Full width' => PageLayout::FullWidth->value,
                    'Landing page' => PageLayout::Landing->value,
                ],
            ])
            ->add('visible', ChoiceType::class, [
                'label' => 'Online',
                'choices' => ['Yes' => 1, 'No' => 0],
                'expanded' => true,
            ])
            ->add('publishAt', DateTimeType::class, [
                'label' => 'Publish on',
                'required' => false,
                'widget' => 'single_text',
                'help' => 'The page stays invisible until this date.',
            ])
            ->add('unpublishAt', DateTimeType::class, [
                'label' => 'Unpublish on',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('html', TextareaType::class, [
                'label' => 'Content',
                'required' => false,
                'attr' => ['rows' => 14],
            ])
            ->add('metaTitle', TextType::class, ['label' => 'Meta title', 'required' => false])
            ->add('metaDescription', TextareaType::class, ['label' => 'Meta description', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('ogTitle', TextType::class, ['label' => 'Social title', 'required' => false])
            ->add('ogDescription', TextareaType::class, ['label' => 'Social description', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('twitterCard', ChoiceType::class, [
                'label' => 'Twitter card',
                'required' => false,
                'placeholder' => 'Default',
                'choices' => ['Summary' => 'summary', 'Summary with large image' => 'summary_large_image'],
            ])
            ->add('canonical', UrlType::class, [
                'label' => 'Canonical URL',
                'required' => false,
                'help' => 'Only fill this in to point search engines at another page.',
            ])
            ->add('noindex', ChoiceType::class, [
                'label' => 'Search engine indexing',
                'choices' => ['Index this page' => 0, 'Do not index (noindex)' => 1],
                'expanded' => true,
            ])
            ->add('nofollow', ChoiceType::class, [
                'label' => 'Link following',
                'choices' => ['Follow links' => 0, 'Do not follow links (nofollow)' => 1],
                'expanded' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'translation_domain' => 'theliacms',
                'parent_choices' => [],
            ])
            ->setAllowedTypes('parent_choices', 'array');
    }
}
