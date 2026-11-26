-- 2.42.3: a second generation of a page, in the same language, is refused by
-- the database itself. Until now the guard read the ledger, then wrote the
-- row of the call some lines later: two requests made together both passed
-- and both paid the model.
--
-- `running_claim` is 1 while a generation of a page runs and NULL otherwise,
-- the unique index over the target, the language and the claim lets a second
-- claim fail (NULLs never collide, so a finished run blocks nothing). Texts
-- and components do not claim.
--
-- Written to be replayable, valid on MySQL and MariaDB: each step checks
-- `information_schema` first, and a second play changes nothing.

-- 1. The index cannot be created while two rows claim the same page: of the
-- calls still running for a page and a language, only the newest stays.
UPDATE `cms_page_generation` AS `older`
    INNER JOIN `cms_page_generation` AS `newer`
        ON `newer`.`target_type` = `older`.`target_type`
        AND `newer`.`target_id` = `older`.`target_id`
        AND `newer`.`locale` = `older`.`locale`
        AND `newer`.`id` > `older`.`id`
        AND `newer`.`status` = 'running'
        AND `newer`.`kind` <> 'text'
SET `older`.`status` = 'interrupted'
WHERE `older`.`target_type` = 'page'
    AND `older`.`status` = 'running'
    AND `older`.`kind` <> 'text';

-- 2. The column.
SET @cms_claim_column := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_generation' AND column_name = 'running_claim'
);

SET @cms_claim_add_column := IF(
    @cms_claim_column = 0,
    'ALTER TABLE `cms_page_generation` ADD COLUMN `running_claim` TINYINT NULL',
    'DO 0'
);

PREPARE cms_claim_add_column_statement FROM @cms_claim_add_column;

EXECUTE cms_claim_add_column_statement;

DEALLOCATE PREPARE cms_claim_add_column_statement;

-- 3. The calls still running hold their page.
UPDATE `cms_page_generation`
SET `running_claim` = 1
WHERE `target_type` = 'page' AND `status` = 'running' AND `kind` <> 'text' AND `running_claim` IS NULL;

-- 4. The index.
SET @cms_claim_index := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_generation' AND index_name = 'unq_cms_page_generation_running'
);

SET @cms_claim_add_index := IF(
    @cms_claim_index = 0,
    'ALTER TABLE `cms_page_generation` ADD UNIQUE INDEX `unq_cms_page_generation_running` (`target_type`, `target_id`, `locale`, `running_claim`)',
    'DO 0'
);

PREPARE cms_claim_add_index_statement FROM @cms_claim_add_index;

EXECUTE cms_claim_add_index_statement;

DEALLOCATE PREPARE cms_claim_add_index_statement;
