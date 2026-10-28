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

--
-- 2.4.0, second part: the mark of a generated content (AI Act, article 50(2)).
-- The stored form is the JSON of `AiProvenance::toArray()` of the core, NULL for
-- a content a person wrote. It lives in a column of its own because the
-- sanitizer of the published HTML removes every `data-*` attribute it does not
-- know: the template prints the mark when the page is served. A row written
-- before this update has none, and stays unmarked.
--
-- Same replayable form as above, one column at a time.

SET @cms_page_content_draft_provenance_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_content' AND column_name = 'draft_provenance'
);

SET @cms_page_content_draft_provenance_add := IF(
    0 = @cms_page_content_draft_provenance_exists,
    'ALTER TABLE `cms_page_content` ADD COLUMN `draft_provenance` LONGTEXT AFTER `draft_updated_by`',
    'DO 0'
);

PREPARE cms_page_content_draft_provenance_statement FROM @cms_page_content_draft_provenance_add;

EXECUTE cms_page_content_draft_provenance_statement;

DEALLOCATE PREPARE cms_page_content_draft_provenance_statement;

SET @cms_page_content_published_provenance_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_content' AND column_name = 'published_provenance'
);

SET @cms_page_content_published_provenance_add := IF(
    0 = @cms_page_content_published_provenance_exists,
    'ALTER TABLE `cms_page_content` ADD COLUMN `published_provenance` LONGTEXT AFTER `draft_provenance`',
    'DO 0'
);

PREPARE cms_page_content_published_provenance_statement FROM @cms_page_content_published_provenance_add;

EXECUTE cms_page_content_published_provenance_statement;

DEALLOCATE PREPARE cms_page_content_published_provenance_statement;

SET @cms_item_content_draft_provenance_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_item_content' AND column_name = 'draft_provenance'
);

SET @cms_item_content_draft_provenance_add := IF(
    0 = @cms_item_content_draft_provenance_exists,
    'ALTER TABLE `cms_item_content` ADD COLUMN `draft_provenance` LONGTEXT AFTER `draft_updated_by`',
    'DO 0'
);

PREPARE cms_item_content_draft_provenance_statement FROM @cms_item_content_draft_provenance_add;

EXECUTE cms_item_content_draft_provenance_statement;

DEALLOCATE PREPARE cms_item_content_draft_provenance_statement;

SET @cms_item_content_published_provenance_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_item_content' AND column_name = 'published_provenance'
);

SET @cms_item_content_published_provenance_add := IF(
    0 = @cms_item_content_published_provenance_exists,
    'ALTER TABLE `cms_item_content` ADD COLUMN `published_provenance` LONGTEXT AFTER `draft_provenance`',
    'DO 0'
);

PREPARE cms_item_content_published_provenance_statement FROM @cms_item_content_published_provenance_add;

EXECUTE cms_item_content_published_provenance_statement;

DEALLOCATE PREPARE cms_item_content_published_provenance_statement;

SET @cms_page_revision_provenance_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_revision' AND column_name = 'provenance'
);

SET @cms_page_revision_provenance_add := IF(
    0 = @cms_page_revision_provenance_exists,
    'ALTER TABLE `cms_page_revision` ADD COLUMN `provenance` LONGTEXT AFTER `html`',
    'DO 0'
);

PREPARE cms_page_revision_provenance_statement FROM @cms_page_revision_provenance_add;

EXECUTE cms_page_revision_provenance_statement;

DEALLOCATE PREPARE cms_page_revision_provenance_statement;

SET @cms_item_revision_provenance_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_item_revision' AND column_name = 'provenance'
);

SET @cms_item_revision_provenance_add := IF(
    0 = @cms_item_revision_provenance_exists,
    'ALTER TABLE `cms_item_revision` ADD COLUMN `provenance` LONGTEXT AFTER `html`',
    'DO 0'
);

PREPARE cms_item_revision_provenance_statement FROM @cms_item_revision_provenance_add;

EXECUTE cms_item_revision_provenance_statement;

DEALLOCATE PREPARE cms_item_revision_provenance_statement;

SET @cms_component_provenance_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_component' AND column_name = 'provenance'
);

SET @cms_component_provenance_add := IF(
    0 = @cms_component_provenance_exists,
    'ALTER TABLE `cms_component` ADD COLUMN `provenance` LONGTEXT AFTER `css`',
    'DO 0'
);

PREPARE cms_component_provenance_statement FROM @cms_component_provenance_add;

EXECUTE cms_component_provenance_statement;

DEALLOCATE PREPARE cms_component_provenance_statement;

SET @cms_page_generation_provenance_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_generation' AND column_name = 'provenance'
);

SET @cms_page_generation_provenance_add := IF(
    0 = @cms_page_generation_provenance_exists,
    'ALTER TABLE `cms_page_generation` ADD COLUMN `provenance` LONGTEXT AFTER `document`',
    'DO 0'
);

PREPARE cms_page_generation_provenance_statement FROM @cms_page_generation_provenance_add;

EXECUTE cms_page_generation_provenance_statement;

DEALLOCATE PREPARE cms_page_generation_provenance_statement;
