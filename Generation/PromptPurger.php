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

use Propel\Runtime\Propel;
use Thelia\Domain\Ai\Gateway\AiGateway;

/**
 * Empties the prompts the ledger keeps once the core no longer keeps its own.
 *
 * A row of the ledger holds the prompt of its call (the brief and what it
 * read of the site) so an editor can iterate from it. The core already decides
 * how long a prompt lives, per usage: it empties the prompt of its usage log at
 * the end of that time, and never stores it when the shop asked for none. The
 * ledger follows the core instead of adding a second duration: a prompt goes
 * when the row of the usage log it points at holds none or is gone. A row
 * without such a pointer (written before 2.4.0, or a call refused before it
 * reached the core) goes after the default retention of the core.
 *
 * Only the prompt goes: the brief the editor wrote, the document and the cost
 * stay, they are the history of the page and the spend of the site.
 */
final readonly class PromptPurger
{
    /**
     * @return int the prompts emptied, or that would be when $dryRun
     */
    public function purge(bool $dryRun, ?\DateTimeImmutable $now = null): int
    {
        $now ??= new \DateTimeImmutable();
        $cutoff = $now->modify(\sprintf('-%d days', AiGateway::DEFAULT_PROMPT_RETENTION_DAYS))->format('Y-m-d H:i:s');
        $condition = <<<'SQL'
            `prompt` IS NOT NULL AND (
                (`log_id` IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `ai_usage_log` WHERE `ai_usage_log`.`id` = `cms_page_generation`.`log_id` AND `ai_usage_log`.`prompt` IS NOT NULL))
                OR (`log_id` IS NULL AND `created_at` < :cutoff)
            )
            SQL;
        $connection = Propel::getConnection('TheliaMain');
        $statement = $connection->prepare($dryRun
            ? 'SELECT COUNT(*) FROM `cms_page_generation` WHERE '.$condition
            : 'UPDATE `cms_page_generation` SET `prompt` = NULL WHERE '.$condition);
        $statement->execute([':cutoff' => $cutoff]);

        return $dryRun ? (int) $statement->fetchColumn() : $statement->rowCount();
    }
}
