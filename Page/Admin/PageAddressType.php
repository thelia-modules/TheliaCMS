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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The slug of a page, with the address the server proposes for it.
 *
 * Rendered as a plain text input: `assets/page-address.js` adds the button that
 * fills it from the title, the whole address under it, and the notice that the
 * address no longer follows the title. Without the script the field is what it
 * always was. The proposal is computed by the server and written nowhere: the
 * address changes when the form is saved, not before.
 */
final class PageAddressType extends AbstractType
{
    /**
     * Optional, as for CmsPageType: the form is also built without a container.
     */
    public function __construct(
        private readonly ?TranslatorInterface $translator = null,
    ) {
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        if (null === $options['proposal_url']) {
            return;
        }

        // Ids of the sibling fields, as Symfony names them: the id of the form
        // they share, an underscore, their name.
        $prefix = null === $view->parent ? '' : $view->parent->vars['id'].'_';

        $view->vars['attr'] += [
            'data-cms-page-address' => '',
            'data-proposal-url' => $options['proposal_url'],
            'data-page-id' => (string) $options['page_id'],
            'data-title-field' => $prefix.$options['title_field'],
            'data-parent-field' => $prefix.$options['parent_field'],
            'data-messages' => json_encode([
                'regenerate' => $this->translate('Regenerate from the title'),
                'address' => $this->translate('Address: %address%'),
                'suffixed' => $this->translate('Another page or language already answers on this address: a number was added to it.'),
                'stale' => $this->translate('The address no longer follows the title.'),
                'failed' => $this->translate('The address could not be computed.'),
            ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'required' => false,
                'proposal_url' => null,
                'page_id' => 0,
                'title_field' => 'title',
                'parent_field' => 'parent',
            ])
            ->setAllowedTypes('proposal_url', ['null', 'string'])
            ->setAllowedTypes('page_id', 'int')
            ->setAllowedTypes('title_field', 'string')
            ->setAllowedTypes('parent_field', 'string');
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    private function translate(string $message): string
    {
        return null === $this->translator ? $message : $this->translator->trans($message, [], 'theliacms');
    }
}
