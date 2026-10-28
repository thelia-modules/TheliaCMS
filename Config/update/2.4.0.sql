-- 2.4.0: a call of the generator points at the row of the usage log of the core
-- that holds what it cost: `cms_page_generation.log_id` is `ai_usage_log.id`, the
-- row of the last request of the call. The requests of one call share the
-- `correlation_id` of the log of the core.
--
-- No foreign key: the core may purge its log, and a row of this history
-- outlives it, like `target_id` outlives its page. A row written before 2.4.0
-- has no `log_id`.
--
-- Written to be replayable: a fresh install runs it after TheliaMain.sql, which
-- already has the column. `ADD COLUMN IF NOT EXISTS` is not used: MariaDB
-- understands it and MySQL does not.

SET @cms_generation_log_id_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_generation' AND column_name = 'log_id'
);

SET @cms_generation_log_id_add := IF(
    0 = @cms_generation_log_id_exists,
    'ALTER TABLE `cms_page_generation` ADD COLUMN `log_id` INTEGER DEFAULT NULL AFTER `request_id`, ADD INDEX `idx_cms_page_generation_log` (`log_id`)',
    'DO 0'
);

PREPARE cms_generation_log_id_statement FROM @cms_generation_log_id_add;

EXECUTE cms_generation_log_id_statement;

DEALLOCATE PREPARE cms_generation_log_id_statement;
