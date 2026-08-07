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

namespace TheliaCMS\Form\Admin;

use Propel\Runtime\ActiveQuery\Criteria;
use TheliaCMS\Form\FieldOptions;
use TheliaCMS\Form\RetentionPolicy;
use TheliaCMS\Model\CmsForm;
use TheliaCMS\Model\CmsFormField;
use TheliaCMS\Model\CmsFormFieldQuery;
use TheliaCMS\Model\CmsFormQuery;
use TheliaCMS\Page\Admin\CmsActivityLog;
use TheliaCMS\Partial\PartialCache;
use TheliaCMS\Security\CmsResources;

/**
 * Every write to a form goes through here, so no caller can forget what has to
 * travel with a change: the pages showing the form have to stop serving the old
 * one, and the change has to end up in the activity log.
 */
final readonly class CmsFormWriter
{
    public function __construct(
        private CmsFormRepository $forms,
        private PartialCache $cache,
        private CmsActivityLog $activityLog,
    ) {
    }

    /**
     * @throws \DomainException when the code is already taken by another form
     */
    public function save(CmsForm $form, string $locale, FormSettings $settings): void
    {
        $existing = CmsFormQuery::create()->findOneByCode($settings->code);

        if (null !== $existing && $existing->getId() !== $form->getId()) {
            throw new \DomainException('Another form already uses this code.');
        }

        $wasNew = $form->isNew();

        $form
            ->setCode($settings->code)
            ->setActive($settings->active ? 1 : 0)
            ->setRecipients($settings->recipients)
            ->setStoreSubmissions($settings->storeSubmissions ? 1 : 0)
            ->setRetentionDays(RetentionPolicy::normalize($settings->retentionDays))
            ->setSendReceipt($settings->sendReceipt ? 1 : 0)
            ->setPrivacyPolicyPageId($settings->privacyPolicyPageId)
            ->setLeadEvent($settings->leadEvent ? 1 : 0);

        $form->setLocale($locale)
            ->setTitle($settings->title)
            ->setDescription($settings->description)
            ->setSubmitLabel($settings->submitLabel)
            ->setSuccessMessage($settings->successMessage)
            ->setLegalNotice($settings->legalNotice)
            ->setReceiptSubject($settings->receiptSubject)
            ->setReceiptBody($settings->receiptBody);

        $form->save();

        $this->afterWrite($wasNew ? 'CREATE' : 'UPDATE', $form, \sprintf('CMS form "%s" saved', $settings->code));
    }

    /**
     * Forms go to a soft delete like pages and reusable blocks: a page may
     * still be pointing at this one, and its submissions are records somebody
     * may have to answer for.
     */
    public function delete(CmsForm $form): void
    {
        $form->setDeletedAt(new \DateTime())->save();

        $this->afterWrite('DELETE', $form, \sprintf('CMS form "%s" deleted', $form->getCode()));
    }

    /**
     * @throws \DomainException when the name is already used by another field
     */
    public function saveField(CmsForm $form, ?CmsFormField $field, string $locale, FieldData $data): CmsFormField
    {
        if ($this->forms->isFieldNameTaken($form, $data->name, null === $field ? null : (int) $field->getId())) {
            throw new \DomainException('Another field of this form already uses this name.');
        }

        $isNew = null === $field;
        $field ??= (new CmsFormField())->setFormId($form->getId());

        $field
            ->setType($data->type->value)
            ->setName($data->name)
            ->setRequired($data->required ? 1 : 0)
            ->setOptions((new FieldOptions(rows: $data->rows))->encode());

        if ($isNew) {
            $field->setPosition($this->nextPosition($form));
        }

        $field->setLocale($locale)
            ->setLabel($data->label)
            ->setPlaceholder($data->placeholder)
            ->setHelp($data->help)
            ->setChoices($data->type->hasChoices() ? $data->choices : null);

        $field->save();

        $this->afterWrite(
            $isNew ? 'CREATE' : 'UPDATE',
            $form,
            \sprintf('CMS form "%s": field "%s" saved', $form->getCode(), $data->name),
        );

        return $field;
    }

    public function deleteField(CmsForm $form, CmsFormField $field): void
    {
        $name = (string) $field->getName();

        $field->delete();

        $this->afterWrite('DELETE', $form, \sprintf('CMS form "%s": field "%s" removed', $form->getCode(), $name));
    }

    /**
     * Moves a field one slot up or down.
     */
    public function moveField(CmsForm $form, CmsFormField $field, int $direction): void
    {
        $ids = $this->forms->fieldIds($form);
        $offset = array_search((int) $field->getId(), $ids, true);

        if (!\is_int($offset)) {
            return;
        }

        $target = $offset + ($direction < 0 ? -1 : 1);

        if ($target < 0 || $target >= \count($ids)) {
            return;
        }

        [$ids[$offset], $ids[$target]] = [$ids[$target], $ids[$offset]];

        $fields = CmsFormFieldQuery::create()
            ->filterByFormId($form->getId())
            ->filterById($ids, Criteria::IN)
            ->find();

        foreach ($fields as $moved) {
            $position = array_search((int) $moved->getId(), $ids, true);

            if (\is_int($position)) {
                $moved->setPosition($position + 1)->save();
            }
        }

        $this->afterWrite('UPDATE', $form, \sprintf('CMS form "%s": field "%s" moved', $form->getCode(), $field->getName()));
    }

    private function nextPosition(CmsForm $form): int
    {
        $last = CmsFormFieldQuery::create()
            ->filterByFormId($form->getId())
            ->orderByPosition(Criteria::DESC)
            ->findOne();

        return null === $last ? 1 : (int) $last->getPosition() + 1;
    }

    /**
     * A form is rendered as a dynamic block, and those are cached per settings.
     * Without this, a field added in the back office would appear on the site
     * whenever the fragment happened to expire.
     */
    private function afterWrite(string $action, CmsForm $form, string $message): void
    {
        $this->cache->invalidate('cms-form');
        $this->activityLog->record($action, (int) $form->getId(), $message, CmsResources::FORM);
    }
}
