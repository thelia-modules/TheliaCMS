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

namespace TheliaCMS\Documentation;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * What the module lets an editor do, written for an editor rather than for
 * the developer of the site: pages, templates, components, skills, settings.
 *
 * One page of text per language, picked from the language of the back
 * office; a language with no text of its own reads the English one.
 */
#[Route('/admin/cms/documentation', name: 'admin.cms.documentation')]
final readonly class CmsDocumentationController
{
    private const string TEMPLATE = '@TheliaCMSModule/backOffice/default-twig/documentation/index.html.twig';

    /** The languages the documentation is written in, by the prefix of a locale. */
    private const array WRITTEN_IN = ['fr' => 'fr_FR', 'en' => 'en_US'];

    public function __construct(
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function show(): Response
    {
        return new Response($this->twig->render(self::TEMPLATE, [
            'text' => \sprintf('@TheliaCMSModule/backOffice/default-twig/documentation/_%s.html.twig', $this->language()),
        ]));
    }

    private function language(): string
    {
        $locale = $this->translator instanceof LocaleAwareInterface ? $this->translator->getLocale() : 'en_US';

        return self::WRITTEN_IN[strtolower(substr($locale, 0, 2))] ?? self::WRITTEN_IN['en'];
    }
}
