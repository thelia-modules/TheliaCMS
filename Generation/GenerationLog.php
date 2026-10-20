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

namespace TheliaCMS\Generation;

use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Security\SecurityContext;
use TheliaCMS\Model\CmsPageGeneration;
use TheliaCMS\Model\CmsPageGenerationQuery;

/**
 * The ledger: one row per call, whatever its outcome. The history an editor
 * iterates from, and the spend the site reads.
 */
final readonly class GenerationLog
{
    public const string STATUS_DONE = 'done';
    public const string STATUS_REFUSED = 'refused';
    public const string STATUS_INVALID = 'invalid';
    public const string STATUS_FAILED = 'failed';

    /** A change made by hand, recorded with the calls so the history reads in order. */
    public const string KIND_MANUAL = 'manual';

    public function __construct(
        private SecurityContext $securityContext,
        private Pricing $pricing,
    ) {
    }

    public function record(
        GenerationRequest $request,
        string $status,
        string $prompt,
        string $model,
        Usage $usage,
        int $turns,
        int $durationMs,
        ?string $requestId = null,
        ?string $stopReason = null,
        ?string $stopCategory = null,
        ?string $error = null,
        ?string $document = null,
    ): int {
        $row = (new CmsPageGeneration())
            ->setTargetType($request->target->type)
            ->setTargetId($request->target->id)
            ->setLocale($request->locale)
            ->setKind($request->kind)
            ->setPrompt($prompt)
            ->setModel($model)
            ->setRequestId($requestId)
            ->setStatus($status)
            ->setStopReason($stopReason)
            ->setStopCategory($stopCategory)
            ->setInputTokens($usage->inputTokens)
            ->setOutputTokens($usage->outputTokens)
            ->setCacheReadTokens($usage->cacheReadTokens)
            ->setCacheWriteTokens($usage->cacheWriteTokens)
            ->setTurns($turns)
            ->setCostUsd(number_format($this->pricing->cost($model, $usage), 6, '.', ''))
            ->setDurationMs($durationMs)
            ->setError(null !== $error ? mb_substr($error, 0, 60000) : null)
            ->setDocument($document)
            ->setCreatedBy($this->securityContext->getAdminUser()?->getId());

        $row->save();

        return (int) $row->getId();
    }

    /**
     * A change made by hand on the document: no call, no cost, and a row all
     * the same, so the history reads in order and the next request starts
     * from the corrected text.
     */
    public function recordManual(GenerationTarget $target, string $locale, string $document, string $note): int
    {
        $row = (new CmsPageGeneration())
            ->setTargetType($target->type)
            ->setTargetId($target->id)
            ->setLocale($locale)
            ->setKind(self::KIND_MANUAL)
            ->setPrompt($note)
            ->setStatus(self::STATUS_DONE)
            ->setCostUsd('0.000000')
            ->setDocument($document)
            ->setCreatedBy($this->securityContext->getAdminUser()?->getId());

        $row->save();

        return (int) $row->getId();
    }

    /**
     * The calls made for a target in a language, the latest first.
     *
     * @return list<CmsPageGeneration>
     */
    public function history(GenerationTarget $target, string $locale, int $limit = 50): array
    {
        return array_values(iterator_to_array(
            CmsPageGenerationQuery::create()
                ->filterByTargetType($target->type)
                ->filterByTargetId($target->id)
                ->filterByLocale($locale)
                ->orderByCreatedAt(Criteria::DESC)
                ->orderById(Criteria::DESC)
                ->limit($limit)
                ->find(),
            false,
        ));
    }

    /**
     * One row of the ledger, if it belongs to the target: a screen shows the
     * outcome of the call it just made from its id, and an id of another
     * page's call says nothing here.
     */
    public function find(int $id, GenerationTarget $target, string $locale): ?CmsPageGeneration
    {
        return CmsPageGenerationQuery::create()
            ->filterById($id)
            ->filterByTargetType($target->type)
            ->filterByTargetId($target->id)
            ->filterByLocale($locale)
            ->findOne();
    }

    /**
     * What the site spent since a date, in US dollars.
     */
    public function spentSince(\DateTimeInterface $since): float
    {
        $sum = CmsPageGenerationQuery::create()
            ->filterByCreatedAt($since, Criteria::GREATER_EQUAL)
            ->withColumn('SUM(cms_page_generation.cost_usd)', 'spent')
            ->select(['spent'])
            ->findOne();

        return (float) ($sum ?? 0);
    }
}
