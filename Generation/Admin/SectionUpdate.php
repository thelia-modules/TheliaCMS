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

namespace TheliaCMS\Generation\Admin;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheliaCMS\Content\Document;
use TheliaCMS\Content\InvalidDocumentException;
use TheliaCMS\Content\SectionCatalog;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\TheliaCMS;

/**
 * A section changed by hand: the form of its props, read back into the
 * document, stored through the writer like a generated one, and written to
 * the ledger as a manual change so the next request starts from it.
 */
final readonly class SectionUpdate
{
    public function __construct(
        private SectionFormBuilder $forms,
        private SectionCatalog $catalog,
        private GenerationLog $log,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param \Closure(Document): void $save stores the document; throws InvalidDocumentException when the site refuses it
     *
     * @return list<string> the problems, empty when the section was saved
     */
    public function apply(Request $request, ContentSubject $subject, Document $current, \Closure $save): array
    {
        $sectionId = self::postedSection($request);
        $section = null === $sectionId ? null : $current->section($sectionId);

        if (null === $section) {
            return [$this->translator->trans('This section is no longer in the document: reload the screen.', [], TheliaCMS::DOMAIN_NAME)];
        }

        $definition = $this->catalog->find($section->component);

        if (null === $definition) {
            return [$this->translator->trans('This section uses a component the site no longer has.', [], TheliaCMS::DOMAIN_NAME)];
        }

        $form = $this->forms->build($definition, $section, $subject->url('update'));
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return [$this->translator->trans('Nothing was sent.', [], TheliaCMS::DOMAIN_NAME)];
        }

        if (!$form->isValid()) {
            $problems = [];

            foreach ($form->getErrors(true) as $error) {
                $problems[] = (string) $error->getMessage();
            }

            return $problems ?: [$this->translator->trans('The form could not be read.', [], TheliaCMS::DOMAIN_NAME)];
        }

        $document = $current->withSection($section->withProps(SectionFormBuilder::propsFrom((array) $form->getData(), $definition->schema()->props)));

        try {
            $save($document);
        } catch (InvalidDocumentException $exception) {
            return $exception->problems;
        }

        $this->log->recordManual($subject->target, $subject->locale, $document->toJson(), \sprintf('Section "%s" edited by hand.', $section->id));

        return [];
    }

    private static function postedSection(Request $request): ?string
    {
        foreach ($request->request->all() as $name => $fields) {
            if (\is_string($name) && str_starts_with($name, SectionFormBuilder::PREFIX) && \is_array($fields) && \is_string($fields['section'] ?? null)) {
                return $fields['section'];
            }
        }

        return null;
    }
}
