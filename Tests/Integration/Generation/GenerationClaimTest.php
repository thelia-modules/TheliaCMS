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

namespace TheliaCMS\Tests\Integration\Generation;

use Propel\Runtime\Connection\ConnectionInterface;
use TheliaCMS\Generation\Brief;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\GenerationTarget;
use TheliaCMS\Generation\GenerationUnavailableException;
use TheliaCMS\Generation\Usage;
use TheliaCMS\Model\CmsPageGenerationQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;
use TheliaCMS\TheliaCMS;

/**
 * One generation of a page runs at a time, whatever the number of requests:
 * the ledger holds the page with a claim the database makes unique, and gives
 * it back when the call ends.
 */
final class GenerationClaimTest extends CmsIntegrationTestCase
{
    /** Ids no page of a test site has: the migration cases write rows by hand. */
    private const int SEEDED_PAGE = 9_000_001;
    private const int OTHER_SEEDED_PAGE = 9_000_002;

    /** An ALTER TABLE commits on its own: no transaction, and tearDown() cleans by hand. */
    protected bool $useTransaction = false;

    private GenerationLog $log;

    private int $lastRowBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lastRowBefore = $this->scalar('SELECT COALESCE(MAX(id), 0) FROM cms_page_generation');
        $this->log = $this->getService(GenerationLog::class);
    }

    /**
     * Sabotage: replace the body of claim() by `return $this->begin($request, $prompt);`.
     */
    public function testASecondClaimOnTheSamePageAndLanguageIsRefused(): void
    {
        $page = $this->createPage('Services', published: false);
        $first = $this->log->claim($this->request($page), 'prompt');

        $refusal = $this->refused(fn () => $this->log->claim($this->request($page), 'prompt'));

        self::assertMatchesRegularExpression('/^A generation is already running for this page in this language \(started at \d\d:\d\d by .+\)\.$/', $refusal->getMessage());
        self::assertSame(1, CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->count(), 'A refusal writes no row.');
        self::assertSame(1, $this->claimOf($first));
    }

    /**
     * Sabotage: leave `->filterByLocale($locale)` out of runningFor().
     */
    public function testAnotherLanguageTargetOrTypeIsNotHeldUp(): void
    {
        $page = $this->createPage('Services', published: false);
        $other = $this->createPage('Autre', published: false);
        $this->log->claim($this->request($page), 'prompt');

        $french = GenerationRequest::fromBrief(GenerationTarget::page($page, 'fr_FR'), 'fr_FR', new Brief('Un texte.'));
        $product = GenerationRequest::fromBrief(GenerationTarget::field('product', (int) $page->getId(), 'Produit', $this->locale()), $this->locale(), new Brief('Un texte.'));

        self::assertGreaterThan(0, $this->log->claim($french, 'prompt'), 'Another language.');
        self::assertGreaterThan(0, $this->log->claim($this->request($other), 'prompt'), 'Another target.');
        self::assertGreaterThan(0, $this->log->claim($product, 'prompt'), 'Another type.');
    }

    /**
     * Sabotage: drop `->setRunningClaim(null)` from record().
     */
    public function testThePageIsFreeAgainOnceTheCallIsRecordedWhateverItsOutcome(): void
    {
        foreach ([GenerationLog::STATUS_DONE, GenerationLog::STATUS_FAILED] as $status) {
            $page = $this->createPage('Services '.$status, published: false);
            $id = $this->log->claim($this->request($page), 'prompt');
            $this->log->record($this->request($page), $status, 'prompt', 'model', new Usage(), 1, 10, 0.0, null, rowId: $id);

            self::assertNull($this->claimOf($id), $status.': the claim goes back.');
            self::assertGreaterThan($id, $this->log->claim($this->request($page), 'prompt'), $status.': a new call may start.');
        }
    }

    /**
     * Sabotage: drop `'RunningClaim' => null` from interruptStale().
     */
    public function testThePageIsFreeAgainOnceTheCallWasFoundDead(): void
    {
        $page = $this->createPage('Services', published: false);
        $id = $this->log->claim($this->request($page), 'prompt');
        CmsPageGenerationQuery::create()->filterById($id)->update(['UpdatedAt' => date('Y-m-d H:i:s', time() - GenerationLog::STALE_AFTER - 60)]);

        GenerationLog::interruptStale('page', (int) $page->getId(), $this->locale());

        self::assertSame(GenerationLog::STATUS_INTERRUPTED, CmsPageGenerationQuery::create()->findPk($id)?->getStatus());
        self::assertNull($this->claimOf($id));
        self::assertGreaterThan($id, $this->log->claim($this->request($page), 'prompt'));
    }

    /**
     * Sabotage: call `claim()` from begin().
     */
    public function testTheRowOfATextOrAComponentHoldsNothing(): void
    {
        $page = $this->createPage('Services', published: false);
        $text = GenerationRequest::text(GenerationTarget::page($page, $this->locale()), $this->locale(), new Brief('Un texte.'));

        $id = $this->log->begin($text, 'prompt');
        $component = $this->log->beginComponent((int) $page->getId(), $this->locale(), 'brief', 'Un composant.');

        self::assertNull($this->claimOf($id));
        self::assertNull($this->claimOf($component));
    }

    /**
     * What makes the refusal certain: the index, not the read before it.
     *
     * Sabotage: leave the index out of Config/TheliaMain.sql and replay the schema.
     */
    public function testTheDatabaseItselfRefusesTwoClaimsOnOnePage(): void
    {
        $connection = $this->getPropelConnection();
        $insert = "INSERT INTO cms_page_generation (target_type, target_id, locale, kind, status, running_claim) VALUES ('page', %d, 'en_US', 'brief', 'running', %s)";
        $connection->exec(\sprintf($insert, self::SEEDED_PAGE, '1'));
        $connection->exec(\sprintf($insert, self::SEEDED_PAGE, 'NULL'));
        $connection->exec(\sprintf($insert, self::SEEDED_PAGE, 'NULL'));

        try {
            $connection->exec(\sprintf($insert, self::SEEDED_PAGE, '1'));
            self::fail('The second claim should have been refused.');
        } catch (\Throwable $exception) {
            $cause = $exception->getPrevious() ?? $exception;
            self::assertStringContainsString(GenerationLog::CLAIM_INDEX, $exception->getMessage().$cause->getMessage());
        }
    }

    /**
     * The table of the site before the update: no column, no index, and
     * several calls running for one page.
     *
     * Sabotage: empty 2.42.3.sql, or drop the first UPDATE of it.
     */
    public function testTheUpdateKeepsTheNewestRunningCallAndInterruptsTheOthers(): void
    {
        $this->useTheTableOfTheSiteBeforeTheUpdate();
        $seeded = $this->seed();

        $this->migrate();
        $first = $this->snapshot();

        self::assertSame(
            [
                'older' => ['interrupted', null],
                'middle' => ['interrupted', null],
                'newest' => ['running', '1'],
                'french' => ['running', '1'],
                'done' => ['done', null],
                'text' => ['running', null],
                'component-old' => ['running', null],
                'component-new' => ['running', null],
            ],
            $this->readable($seeded, $first),
        );
        self::assertSame(1, $this->scalar("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'cms_page_generation' AND index_name = '".GenerationLog::CLAIM_INDEX."' AND column_name = 'running_claim'"));

        $this->migrate();

        self::assertSame($first, $this->snapshot(), 'A second play changes nothing.');
    }

    protected function tearDown(): void
    {
        $connection = $this->getPropelConnection();
        $connection->exec('DELETE FROM cms_page_generation WHERE id > '.$this->lastRowBefore);
        // Put back what a case took away (idempotent: nothing to do when it is there).
        $this->migrate();

        parent::tearDown();
    }

    private function request(\TheliaCMS\Model\CmsPage $page): GenerationRequest
    {
        return GenerationRequest::fromBrief(GenerationTarget::page($page, $this->locale()), $this->locale(), new Brief('Une page qui présente nos services.'));
    }

    private function refused(\Closure $claim): GenerationUnavailableException
    {
        try {
            $claim();
        } catch (GenerationUnavailableException $exception) {
            return $exception;
        }

        self::fail('The claim should have been refused.');
    }

    private function claimOf(int $rowId): ?int
    {
        $claim = $this->getPropelConnection()->query('SELECT running_claim FROM cms_page_generation WHERE id = '.$rowId)->fetchColumn();

        return null === $claim || false === $claim ? null : (int) $claim;
    }

    private function useTheTableOfTheSiteBeforeTheUpdate(): void
    {
        $connection = $this->getPropelConnection();
        $connection->exec('ALTER TABLE cms_page_generation DROP INDEX '.GenerationLog::CLAIM_INDEX);
        $connection->exec('ALTER TABLE cms_page_generation DROP COLUMN running_claim');
    }

    /**
     * @return array<string, int> the ids of the rows written, by name
     */
    private function seed(): array
    {
        $connection = $this->getPropelConnection();
        $rows = [
            'older' => ['page', self::SEEDED_PAGE, 'en_US', 'brief', 'running'],
            'middle' => ['page', self::SEEDED_PAGE, 'en_US', 'revision', 'running'],
            'newest' => ['page', self::SEEDED_PAGE, 'en_US', 'brief', 'running'],
            'french' => ['page', self::SEEDED_PAGE, 'fr_FR', 'brief', 'running'],
            'done' => ['page', self::SEEDED_PAGE, 'en_US', 'brief', 'done'],
            'text' => ['page', self::SEEDED_PAGE, 'en_US', 'text', 'running'],
            'component-old' => ['component', self::OTHER_SEEDED_PAGE, 'en_US', 'brief', 'running'],
            'component-new' => ['component', self::OTHER_SEEDED_PAGE, 'en_US', 'brief', 'running'],
        ];
        $ids = [];

        foreach ($rows as $name => [$type, $target, $locale, $kind, $status]) {
            $connection->exec(\sprintf("INSERT INTO cms_page_generation (target_type, target_id, locale, kind, status, created_at, updated_at) VALUES ('%s', %d, '%s', '%s', '%s', NOW(), NOW())", $type, $target, $locale, $kind, $status));
            $ids[$name] = (int) $connection->lastInsertId();
        }

        return $ids;
    }

    /**
     * @return array<int, array{string, string|null}>
     */
    private function snapshot(): array
    {
        $rows = [];

        foreach ($this->getPropelConnection()->query(\sprintf('SELECT id, status, running_claim FROM cms_page_generation WHERE target_id IN (%d, %d) ORDER BY id', self::SEEDED_PAGE, self::OTHER_SEEDED_PAGE))->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[(int) $row['id']] = [(string) $row['status'], null === $row['running_claim'] ? null : (string) $row['running_claim']];
        }

        return $rows;
    }

    /**
     * @param array<string, int>                     $ids
     * @param array<int, array{string, string|null}> $snapshot
     *
     * @return array<string, array{string, string|null}>
     */
    private function readable(array $ids, array $snapshot): array
    {
        return array_map(static fn (int $id): array => $snapshot[$id], $ids);
    }

    private function scalar(string $sql): int
    {
        return (int) $this->getPropelConnection()->query($sql)->fetchColumn();
    }

    private function migrate(): void
    {
        $connection = $this->getPropelConnection();
        self::assertInstanceOf(ConnectionInterface::class, $connection);
        (new TheliaCMS())->update('2.42.2', '2.42.3', $connection);
    }
}
