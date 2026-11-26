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

use Propel\Runtime\Connection\ConnectionManagerSingle;
use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\StandardServiceContainer;
use TheliaCMS\Generation\Brief;
use TheliaCMS\Generation\GenerationLog;
use TheliaCMS\Generation\GenerationRequest;
use TheliaCMS\Generation\GenerationTarget;
use TheliaCMS\Generation\GenerationUnavailableException;
use TheliaCMS\Model\CmsPageGenerationQuery;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * Generations of one page started from several processes at once, each on its
 * own connection: what one PHP process cannot show. Built on the model of
 * `DraftRaceTest`.
 */
final class GenerationClaimRaceTest extends CmsIntegrationTestCase
{
    private const int EDITORS = 6;

    protected bool $useTransaction = false;

    private string $results = '';

    private int $lastRowBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();

        if (!\function_exists('pcntl_fork') || !\function_exists('posix_kill')) {
            self::markTestSkipped('Needs pcntl and posix to start from several processes at once.');
        }

        $this->lastRowBefore = (int) $this->getPropelConnection()->query('SELECT COALESCE(MAX(id), 0) FROM cms_page_generation')->fetchColumn();
        $this->results = sys_get_temp_dir().'/cms-claim-race-'.bin2hex(random_bytes(6));
        mkdir($this->results);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->results.'/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->results);
        $this->getPropelConnection()->exec('DELETE FROM cms_page_generation WHERE id > '.$this->lastRowBefore);

        parent::tearDown();
    }

    /**
     * Sabotage: in claim(), put back `runningFor()` then `begin()` (no claim, no index).
     */
    public function testRequestsMadeAtOnceLetExactlyOneGenerationStart(): void
    {
        $page = $this->createPage('Course', published: false);
        $request = GenerationRequest::fromBrief(GenerationTarget::page($page, $this->locale()), $this->locale(), new Brief('Une page de cours.'));
        $configuration = $this->connectionConfiguration();
        // Every process waits for the same instant, so the claims hit the
        // database together rather than in the order the processes were forked.
        $start = microtime(true) + 1.0;
        $children = [];

        for ($editor = 0; $editor < self::EDITORS; ++$editor) {
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid, 'Could not fork.');

            if (0 === $pid) {
                $this->inAChild($configuration, (string) $editor, function () use ($request, $start): string {
                    $log = $this->getService(GenerationLog::class);
                    // Every process has read "nothing running" before any of them writes.
                    $seen = $log->runningFor($request->target, $request->locale);
                    time_nanosleep(0, max(0, (int) (($start - microtime(true)) * 1e9)));

                    if (null !== $seen) {
                        return 'refused';
                    }

                    try {
                        $log->claim($request, 'prompt');

                        return 'claimed';
                    } catch (GenerationUnavailableException) {
                        return 'refused';
                    }
                });
            }

            $children[] = $pid;
        }

        foreach ($children as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $answers = $this->answers();

        self::assertCount(self::EDITORS, $answers, 'A process died before answering: '.implode(', ', $answers));
        self::assertCount(1, array_keys($answers, 'claimed', true), 'Claims made at once: '.implode(', ', $answers));
        self::assertCount(self::EDITORS - 1, array_keys($answers, 'refused', true), implode(', ', $answers));
        self::assertSame(1, CmsPageGenerationQuery::create()->filterByTargetId($page->getId())->filterByStatus(GenerationLog::STATUS_RUNNING)->count(), 'One row runs.');
        self::assertSame(1, (int) $this->getPropelConnection()->query('SELECT COUNT(*) FROM cms_page_generation WHERE running_claim = 1 AND target_id = '.$page->getId())->fetchColumn(), 'One row holds the page.');
    }

    /**
     * Runs in the forked process and never returns; see DraftRaceTest.
     *
     * @param array<string, mixed> $configuration
     * @param \Closure(): string   $work
     */
    private function inAChild(array $configuration, string $name, \Closure $work): never
    {
        // Held until the SIGKILL: dropped, its destructor would close the socket the parent uses.
        $inherited = Propel::getServiceContainer()->getWriteConnection('TheliaMain');

        try {
            $container = Propel::getServiceContainer();
            self::assertInstanceOf(StandardServiceContainer::class, $container);
            $manager = new ConnectionManagerSingle('TheliaMain');
            $manager->setConfiguration($configuration);
            $container->setConnectionManager($manager);

            $answer = $work();
        } catch (\Throwable $throwable) {
            $answer = $throwable::class.': '.$throwable->getMessage();
        }

        file_put_contents($this->results.'/answer-'.$name, $answer);

        posix_kill(getmypid(), \SIGKILL);

        exit(1);
    }

    /**
     * @return list<string>
     */
    private function answers(): array
    {
        return array_map(static fn (string $file): string => (string) file_get_contents($file), glob($this->results.'/answer-*') ?: []);
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfiguration(): array
    {
        $manager = Propel::getServiceContainer()->getConnectionManager('TheliaMain');
        $configuration = $manager instanceof ConnectionManagerSingle ? $manager->getConfiguration() : [];

        if ([] === $configuration) {
            self::markTestSkipped('The connection of the suite was not built from a configuration a child could reuse.');
        }

        return $configuration;
    }
}
