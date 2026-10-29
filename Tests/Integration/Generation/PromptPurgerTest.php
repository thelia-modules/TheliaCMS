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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Maintenance\MaintenancePurgeEvent;
use Thelia\Core\Event\TheliaEvents;
use TheliaCMS\Generation\PromptPurger;
use TheliaCMS\Tests\Integration\CmsIntegrationTestCase;

/**
 * The prompts of the ledger live as long as the ones of the usage log of the
 * core: the core empties its own at the end of their retention, and never
 * stores them when the shop asked for none.
 */
final class PromptPurgerTest extends CmsIntegrationTestCase
{
    public function testAPromptGoesWhenTheCoreNoLongerKeepsItsOwn(): void
    {
        $kept = $this->coreLog('A prompt the core still keeps.');
        $emptied = $this->coreLog(null);
        $ids = [
            'kept' => $this->ledgerRow($kept, 'Prompt of a call whose log keeps it.'),
            'emptied' => $this->ledgerRow($emptied, 'Prompt of a call whose log was emptied.'),
            'gone' => $this->ledgerRow(999999999, 'Prompt of a call whose log was purged.'),
            'old' => $this->ledgerRow(null, 'Prompt written before 2.4.0, 45 days ago.', '-45 days'),
            'recent' => $this->ledgerRow(null, 'Prompt of a call refused before it reached the core.', '-2 days'),
        ];

        $purger = new PromptPurger();
        self::assertSame(3, $purger->purge(dryRun: true), 'Counted, nothing changed.');
        self::assertSame('Prompt of a call whose log was emptied.', $this->prompt($ids['emptied']));

        self::assertSame(3, $purger->purge(dryRun: false));

        self::assertSame('Prompt of a call whose log keeps it.', $this->prompt($ids['kept']));
        self::assertNull($this->prompt($ids['emptied']));
        self::assertNull($this->prompt($ids['gone']));
        self::assertNull($this->prompt($ids['old']));
        self::assertSame('Prompt of a call refused before it reached the core.', $this->prompt($ids['recent']), 'Without a pointer, the default retention of the core applies.');
        self::assertSame('{"text":"brief"}', $this->column($ids['emptied'], 'brief'), 'Only the prompt goes: the brief stays.');
        self::assertSame('{"doc":1}', $this->column($ids['emptied'], 'document'), 'The document stays.');
        self::assertSame(0, $purger->purge(dryRun: false), 'A second run finds nothing.');
    }

    public function testTheMaintenanceCommandRunsIt(): void
    {
        $id = $this->ledgerRow($this->coreLog(null), 'A prompt the core did not keep.');

        $dry = new MaintenancePurgeEvent(true);
        $this->getService(EventDispatcherInterface::class)->dispatch($dry, TheliaEvents::MAINTENANCE_PURGE);
        self::assertStringContainsString('1 to empty', implode(' ', array_filter($dry->getResults(), static fn (string $line): bool => str_contains($line, 'CMS generator prompts'))));
        self::assertNotNull($this->prompt($id), 'A dry run changes nothing.');

        $event = new MaintenancePurgeEvent();
        $this->getService(EventDispatcherInterface::class)->dispatch($event, TheliaEvents::MAINTENANCE_PURGE);

        self::assertStringContainsString('1 emptied', implode(' ', array_filter($event->getResults(), static fn (string $line): bool => str_contains($line, 'CMS generator prompts'))));
        self::assertNull($this->prompt($id));
    }

    private function coreLog(?string $prompt): int
    {
        $connection = $this->getPropelConnection();
        $statement = $connection->prepare("INSERT INTO ai_usage_log (usage_id, module, kind, actor_type, actor_hash, status, prompt, created_at) VALUES ('theliacms.page_generation', 'TheliaCMS', 'completion', 'system', 'x', 'success', :prompt, NOW())");
        $statement->execute([':prompt' => $prompt]);

        return (int) $connection->lastInsertId();
    }

    private function ledgerRow(?int $logId, string $prompt, string $age = 'now'): int
    {
        $connection = $this->getPropelConnection();
        $statement = $connection->prepare("INSERT INTO cms_page_generation (target_type, target_id, locale, kind, status, prompt, brief, document, log_id, created_at, updated_at) VALUES ('page', 1, 'en_US', 'brief', 'done', :prompt, '{\"text\":\"brief\"}', '{\"doc\":1}', :log, :created, :created)");
        $statement->execute([':prompt' => $prompt, ':log' => $logId, ':created' => (new \DateTimeImmutable($age))->format('Y-m-d H:i:s')]);

        return (int) $connection->lastInsertId();
    }

    private function prompt(int $id): ?string
    {
        return $this->column($id, 'prompt');
    }

    private function column(int $id, string $name): ?string
    {
        $value = $this->getPropelConnection()->query(\sprintf('SELECT %s FROM cms_page_generation WHERE id = %d', $name, $id))->fetchColumn();

        return false === $value ? null : $value;
    }
}
