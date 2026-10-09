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
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use TheliaCMS\Media\Admin\CmsMediaType;
use TheliaCMS\Page\PageTypeCode;

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
            // `wysiwyg` is the opt-in class of the back-office rich text
            // editor: the same editor as the summary and description of a
            // core content, when one is installed, a plain textarea otherwise.
            ->add('chapo', TextareaType::class, ['label' => 'Summary', 'required' => false, 'attr' => ['rows' => 3, 'class' => 'wysiwyg']])
            ->add('description', TextareaType::class, ['label' => 'Detailed description', 'required' => false, 'attr' => ['rows' => 8, 'class' => 'wysiwyg']])
            ->add('parent', ChoiceType::class, [
                'label' => 'Parent page',
                'choices' => ['None (top level)' => 0] + $options['parent_choices'],
            ])
            // A type is a code and nothing else: shown as it is, never
            // translated. Managed under CMS > Settings > Page types.
            ->add('pageType', ChoiceType::class, [
                'label' => 'Page type',
                'choices' => array_combine($options['page_type_choices'], $options['page_type_choices']),
                'choice_translation_domain' => false,
                'help' => 'Picks the template the page is displayed with.',
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
                'default_protocol' => 'https',
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
            ])
            ->add('image', ChoiceType::class, [
                'label' => 'Image of the page',
                'required' => false,
                'expanded' => true,
                'placeholder' => 'No image',
                'choices' => $options['image_choices'],
                'choice_label' => static fn (int $imageId): string => (string) $imageId,
            ]);

        // Uploading adds an image to the CMS library, which is the right to
        // create media, not the right to change pages: without it the field is
        // not there, and a request that sends a file anyway is an invalid form.
        if (!$options['allow_image_upload']) {
            return;
        }

        // Wins over the choice above: the file is stored in the CMS library
        // and becomes the image of the page in the same save.
        $builder->add('imageUpload', FileType::class, [
            'label' => 'Or upload a new image',
            'required' => false,
            'constraints' => [new Image(
                mimeTypes: CmsMediaType::ACCEPTED_MIME_TYPES,
                mimeTypesMessage: 'Only JPEG, PNG and WebP images can be uploaded.',
            )],
            'help' => 'It is added to the CMS media library. Describe it there so the page can say what it shows.',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'translation_domain' => 'theliacms',
                'parent_choices' => [],
                'image_choices' => [],
                'page_type_choices' => [PageTypeCode::DEFAULT],
                'allow_image_upload' => false,
            ])
            ->setAllowedTypes('parent_choices', 'array')
            ->setAllowedTypes('image_choices', 'array')
            ->setAllowedTypes('page_type_choices', 'array')
            ->setAllowedTypes('allow_image_upload', 'bool');
    }
}
