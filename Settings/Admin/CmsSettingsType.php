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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use TheliaCMS\Settings\IpAllowlist;
use TheliaCMS\Settings\SiteMode;

final class CmsSettingsType extends AbstractType
{
    public function __construct(
        private readonly IpAllowlist $allowlist,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('siteMode', ChoiceType::class, [
                'label' => 'What this site is',
                'expanded' => true,
                'choices' => [
                    'A shop with content pages' => SiteMode::Commerce->value,
                    'A showcase site' => SiteMode::Showcase->value,
                ],
                // What each mode does is spelled out under the radios by the
                // template: as inline help it runs into the label of the group.
            ])
            ->add('notFoundPageId', ChoiceType::class, [
                'label' => 'Page shown when an address does not exist',
                'required' => false,
                'placeholder' => 'The 404 page of the theme',
                'choices' => $options['page_choices'],
                'help' => 'The page has to be published in the language of the visitor; otherwise the theme takes over.',
            ])
            ->add('maintenanceActive', CheckboxType::class, [
                'label' => 'Close the site for maintenance',
                'required' => false,
                'help' => 'Visitors get a 503 answer, which tells search engines to come back later rather than to drop the page. The back office stays open, and so do the addresses listed below.',
            ])
            ->add('maintenancePageId', ChoiceType::class, [
                'label' => 'Page shown during maintenance',
                'required' => false,
                'placeholder' => 'A plain message from the module',
                'choices' => $options['page_choices'],
            ])
            ->add('maintenanceAllowlist', TextareaType::class, [
                'label' => 'Addresses that keep seeing the site',
                'required' => false,
                'attr' => ['rows' => 4, 'placeholder' => "203.0.113.42\n2001:db8::/32"],
                'help' => 'One IP address or range per line. Yours is shown below.',
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->validateAllowlist(...));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'translation_domain' => 'theliacms',
                'page_choices' => [],
            ])
            ->setAllowedTypes('page_choices', 'array');
    }

    /**
     * An address with a typo in it locks out the very person who typed it, and
     * they only find out with the site already closed.
     */
    private function validateAllowlist(FormEvent $event): void
    {
        $data = $event->getData();

        if (!\is_array($data)) {
            return;
        }

        $entries = array_filter(
            array_map('trim', preg_split('/[\s,;]+/', (string) ($data['maintenanceAllowlist'] ?? '')) ?: []),
            static fn (string $entry): bool => '' !== $entry,
        );

        $rejected = $this->allowlist->rejected(array_values($entries));

        if ([] !== $rejected) {
            $event->getForm()->get('maintenanceAllowlist')->addError(new FormError(
                \sprintf('This is not an IP address or a range: %s', implode(', ', $rejected))
            ));
        }
    }
}
