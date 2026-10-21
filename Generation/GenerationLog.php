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

    /**
     * @param list<WebPageRead> $webPages the pages the call read, counted
     * @param list<string>      $leftOut  what the answer says the page leaves out of the brief
     */
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
        array $webPages = [],
        array $leftOut = [],
    ): int {
        $row = (new CmsPageGeneration())
            ->setTargetType($request->target->type)
            ->setTargetId($request->target->id)
            ->setLocale($request->locale)
            ->setKind($request->kind)
            ->setPrompt($prompt)
            ->setBrief(self::briefJson($request->brief))
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
            ->setWebPages([] === $webPages ? null : json_encode(array_map(static fn (WebPageRead $page): array => $page->toArray(), $webPages), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE))
            ->setLeftOut([] === $leftOut ? null : json_encode($leftOut, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE))
            ->setCreatedBy($this->securityContext->getAdminUser()?->getId());

        $row->save();

        return (int) $row->getId();
    }

    /**
     * A call that made or changed a component of the site. A new component
     * has no row until the editor keeps it: its calls are recorded under the
     * id 0, and count in the spend all the same.
     */
    public function recordComponent(
        int $componentId,
        string $locale,
        string $kind,
        string $request,
        string $status,
        string $model,
        Usage $usage,
        int $turns,
        int $durationMs,
        ?string $requestId = null,
        ?string $stopReason = null,
        ?string $stopCategory = null,
        ?string $error = null,
        ?string $component = null,
    ): int {
        $row = (new CmsPageGeneration())
            ->setTargetType(GenerationTarget::TYPE_COMPONENT)
            ->setTargetId($componentId)
            ->setLocale($locale)
            ->setKind($kind)
            ->setPrompt($request)
            ->setBrief(json_encode(['text' => $request], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR))
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
            ->setDocument($component)
            ->setCreatedBy($this->securityContext->getAdminUser()?->getId());
        $row->save();

        return (int) $row->getId();
    }

    /**
     * What the answer of a call said the page leaves out of the brief. A row
     * written before the column, or by a call that left nothing out, holds
     * nothing.
     *
     * @return list<string>
     */
    public static function leftOutOf(CmsPageGeneration $row): array
    {
        $json = (string) $row->getLeftOut();

        if ('' === $json) {
            return [];
        }

        try {
            $sentences = json_decode($json, true, 2, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($sentences) ? array_values(array_filter($sentences, 'is_string')) : [];
    }

    /**
     * The web pages a call read, as it recorded them. A row written before
     * the column, or by a call that read none, holds none.
     *
     * @return list<WebPageRead>
     */
    public static function webPagesOf(CmsPageGeneration $row): array
    {
        $json = (string) $row->getWebPages();

        if ('' === $json) {
            return [];
        }

        try {
            $rows = json_decode($json, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($rows) ? array_values(array_map(static fn (mixed $page): WebPageRead => WebPageRead::fromArray(\is_array($page) ? $page : []), $rows)) : [];
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
     * The brief the target was last written from in a language, as the
     * editor typed it: the screen puts it back in the form, so a page is
     * written again from its own brief rather than from a blank.
     */
    public function latestBrief(GenerationTarget $target, string $locale): ?Brief
    {
        $row = CmsPageGenerationQuery::create()
            ->filterByTargetType($target->type)
            ->filterByTargetId($target->id)
            ->filterByLocale($locale)
            ->filterByKind(GenerationRequest::KIND_BRIEF)
            ->orderByCreatedAt(Criteria::DESC)
            ->orderById(Criteria::DESC)
            ->findOne();

        $brief = null === $row?->getBrief() ? null : json_decode((string) $row->getBrief(), true);

        if (!\is_array($brief) || !\is_string($brief['text'] ?? null) || '' === trim($brief['text'])) {
            return null;
        }

        return new Brief($brief['text'], \is_string($brief['tone'] ?? null) ? $brief['tone'] : null, \is_string($brief['audience'] ?? null) ? $brief['audience'] : null);
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

    private static function briefJson(Brief $brief): string
    {
        return json_encode(['text' => $brief->text, 'tone' => $brief->tone, 'audience' => $brief->audience], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
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
