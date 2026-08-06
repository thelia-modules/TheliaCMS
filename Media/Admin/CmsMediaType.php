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

namespace TheliaCMS\Media\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;

final class CmsMediaType extends AbstractType
{
    /**
     * SVG is deliberately absent: it is a document that can carry script, and
     * an uploaded one becomes stored cross-site scripting.
     */
    public const array ACCEPTED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Name',
                'required' => false,
                'constraints' => [new Length(max: 255)],
                'help' => 'Only used to find the image again in this library.',
            ])
            ->add('decorative', CheckboxType::class, [
                'label' => 'This image is decorative',
                'required' => false,
                'help' => 'Decorative images are published with an empty alternative text so screen readers skip them. Use it for backgrounds and separators, never for a photo that carries information.',
            ])
            // Declared required so the label says so; the browser is told not
            // to enforce it (the screen renders the form with `novalidate`)
            // because the rule below lifts it for a decorative image.
            ->add('alt', TextType::class, [
                'label' => 'Alternative text',
                'required' => true,
                'constraints' => [new Length(max: 255)],
                'help' => 'What the image shows, for people who cannot see it. Translated per language.',
            ])
            ->add('caption', TextareaType::class, [
                'label' => 'Caption',
                'required' => false,
                'attr' => ['rows' => 2],
                'help' => 'Shown next to the image where a theme displays one. Translated per language.',
            ])
            ->add('tags', TextType::class, [
                'label' => 'Tags',
                'required' => false,
                'help' => 'Comma separated. Tags are how the library is searched until it gets folders.',
            ])
            ->add('file', FileType::class, [
                'label' => 'Replace the file',
                'required' => false,
                'constraints' => [new Image(
                    mimeTypes: self::ACCEPTED_MIME_TYPES,
                    mimeTypesMessage: 'Only JPEG, PNG and WebP images can be uploaded.',
                )],
                'help' => 'The new file keeps the same address, so the pages using this image show it right away.',
            ])
            ->addEventListener(FormEvents::SUBMIT, $this->requireAltUnlessDecorative());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['translation_domain' => 'theliacms']);
    }

    /**
     * Alternative text is mandatory unless the image is declared decorative —
     * and that declaration arrives in the same submission, so the rule cannot
     * be a constraint on the field.
     */
    private function requireAltUnlessDecorative(): \Closure
    {
        return static function (FormEvent $event): void {
            $data = $event->getData();

            if (true === ($data['decorative'] ?? false)) {
                $data['alt'] = null;
                $event->setData($data);

                return;
            }

            if ('' === trim((string) ($data['alt'] ?? ''))) {
                $event->getForm()->get('alt')->addError(new FormError(
                    'Describe the image, or tick "This image is decorative".'
                ));
            }
        };
    }
}
