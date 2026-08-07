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
use TheliaCMS\Form\SubmissionData;
use TheliaCMS\Model\CmsForm;
use TheliaCMS\Model\CmsFormSubmission;
use TheliaCMS\Model\CmsFormSubmissionQuery;

/**
 * Reads the submissions of a form, optionally narrowed to one person.
 */
final readonly class SubmissionRepository
{
    public const int PER_PAGE = 25;

    /**
     * @return list<array{id: int, locale: string, email: ?string, created_at: string, answers: list<array<string, mixed>>}>
     */
    public function page(CmsForm $form, string $email = '', int $page = 1): array
    {
        $query = $this->search($form, $email)
            ->orderByCreatedAt(Criteria::DESC)
            ->orderById(Criteria::DESC)
            ->offset(max(0, $page - 1) * self::PER_PAGE)
            ->limit(self::PER_PAGE);

        return array_map($this->asArray(...), iterator_to_array($query->find(), false));
    }

    public function count(CmsForm $form, string $email = ''): int
    {
        return $this->search($form, $email)->count();
    }

    /**
     * Every submission matching the search, for the export — not one page of
     * them: the point of the export is to hand over everything about a person
     * in one go.
     *
     * @return list<array{id: int, locale: string, email: ?string, created_at: string, answers: list<array<string, mixed>>}>
     */
    public function all(CmsForm $form, string $email = ''): array
    {
        $query = $this->search($form, $email)
            ->orderByCreatedAt(Criteria::DESC)
            ->orderById(Criteria::DESC);

        return array_map($this->asArray(...), iterator_to_array($query->find(), false));
    }

    public function find(CmsForm $form, int $id): ?CmsFormSubmission
    {
        $submission = CmsFormSubmissionQuery::create()
            ->filterByFormId($form->getId())
            ->findPk($id);

        return $submission instanceof CmsFormSubmission ? $submission : null;
    }

    private function search(CmsForm $form, string $email): CmsFormSubmissionQuery
    {
        $query = CmsFormSubmissionQuery::create()->filterByFormId($form->getId());

        $email = trim($email);

        if ('' !== $email) {
            // Partial, because whoever answers a request to be forgotten is
            // reading an address off an email and may only have part of it.
            $query->filterByEmail('%'.$email.'%', Criteria::LIKE);
        }

        return $query;
    }

    /**
     * @return array{id: int, locale: string, email: ?string, created_at: string, answers: list<array<string, mixed>>}
     */
    private function asArray(CmsFormSubmission $submission): array
    {
        return [
            'id' => (int) $submission->getId(),
            'locale' => (string) $submission->getLocale(),
            'email' => $submission->getEmail(),
            'created_at' => $submission->getCreatedAt('Y-m-d H:i:s') ?? '',
            'answers' => SubmissionData::decode($submission->getData()),
        ];
    }
}
