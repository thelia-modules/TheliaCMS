-- 2.0.0: the content of a page becomes a document (see
-- TheliaCMS\Content\Document), rendered to HTML on save and on publish. The
-- columns of the former editor go: the project it reloaded from and the
-- stylesheets it wrote.
--
-- A site coming from 1.x keeps its pages — tree, addresses, SEO,
-- tags, types — and loses their content: the markup of the former editor has
-- no document to be read back from, and serving it without one would leave
-- pages nobody can edit. Those pages are put back to draft, their revisions
-- and search entries removed with them. The release notes say
-- so; an export taken before the update keeps the HTML.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already declares the new shape) and by an update of an
-- existing site. Every statement tolerates being run twice, and the purge only
-- runs where a column of the former editor still exists, which is a 1.x site
-- and never a fresh one.

-- cms_page_content ---------------------------------------------------------

SET @cms_page_content_from_1x := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_content' AND column_name = 'draft_project_data'
);

SET @cms_statement := IF(
    0 < @cms_page_content_from_1x,
    'UPDATE `cms_page_content` SET `draft_html` = NULL, `published_html` = NULL, `published_at` = NULL',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 < @cms_page_content_from_1x,
    'DELETE FROM `cms_page_revision`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 < @cms_page_content_from_1x,
    'DELETE FROM `cms_page_search`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_page_content' AND column_name = 'draft_document'),
    'ALTER TABLE `cms_page_content` ADD COLUMN `draft_document` LONGTEXT AFTER `locale`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_page_content' AND column_name = 'published_document'),
    'ALTER TABLE `cms_page_content` ADD COLUMN `published_document` LONGTEXT AFTER `draft_html`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 < @cms_page_content_from_1x,
    'ALTER TABLE `cms_page_content` DROP COLUMN `draft_project_data`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 < (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_page_content' AND column_name = 'draft_css'),
    'ALTER TABLE `cms_page_content` DROP COLUMN `draft_css`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 < (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_page_content' AND column_name = 'published_css'),
    'ALTER TABLE `cms_page_content` DROP COLUMN `published_css`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

-- cms_page_revision --------------------------------------------------------

SET @cms_statement := IF(
    0 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_page_revision' AND column_name = 'document'),
    'ALTER TABLE `cms_page_revision` ADD COLUMN `document` LONGTEXT AFTER `locale`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 < (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_page_revision' AND column_name = 'project_data'),
    'ALTER TABLE `cms_page_revision` DROP COLUMN `project_data`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 < (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_page_revision' AND column_name = 'css'),
    'ALTER TABLE `cms_page_revision` DROP COLUMN `css`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

-- cms_block, cms_block_i18n, cms_block_content, cms_item_block -------------
-- Blocks are no longer part of the module: the reusable ones and the one of
-- a catalogue item go with their tables. An export taken before the update
-- keeps them; a 2.0.0 module imports the rest of that file and says it left
-- the blocks out.

DROP TABLE IF EXISTS `cms_item_block`;
DROP TABLE IF EXISTS `cms_block_content`;
DROP TABLE IF EXISTS `cms_block_i18n`;
DROP TABLE IF EXISTS `cms_block`;

-- cms_page_template ---------------------------------------------------------
-- Saved page templates are no longer part of the module: the table goes.

DROP TABLE IF EXISTS `cms_page_template`;

-- cms_skill and cms_page_generation ----------------------------------------
-- The skills the generator reads, and its ledger. Created here for a site
-- coming from 1.x; TheliaMain.sql already declares them on a fresh install.

CREATE TABLE IF NOT EXISTS `cms_skill`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `name` VARCHAR(120) NOT NULL,
    `description` VARCHAR(500),
    `body` LONGTEXT,
    `shipped_body` LONGTEXT,
    `origin` VARCHAR(20) DEFAULT 'module' NOT NULL,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `enabled` TINYINT DEFAULT 1 NOT NULL,
    `always` TINYINT DEFAULT 0 NOT NULL,
    `updated_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_skill_code` (`code`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `cms_page_generation`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `target_type` VARCHAR(20) DEFAULT 'page' NOT NULL,
    `target_id` INTEGER NOT NULL,
    `locale` VARCHAR(5) NOT NULL,
    `kind` VARCHAR(20) DEFAULT 'brief' NOT NULL,
    `prompt` LONGTEXT,
    `brief` LONGTEXT,
    `model` VARCHAR(60),
    `request_id` VARCHAR(80),
    `status` VARCHAR(20) DEFAULT 'done' NOT NULL,
    `stop_reason` VARCHAR(30),
    `stop_category` VARCHAR(60),
    `input_tokens` INTEGER DEFAULT 0 NOT NULL,
    `output_tokens` INTEGER DEFAULT 0 NOT NULL,
    `cache_read_tokens` INTEGER DEFAULT 0 NOT NULL,
    `cache_write_tokens` INTEGER DEFAULT 0 NOT NULL,
    `turns` INTEGER DEFAULT 0 NOT NULL,
    `cost_usd` DECIMAL(12,6),
    `duration_ms` INTEGER DEFAULT 0 NOT NULL,
    `error` TEXT,
    `document` LONGTEXT,
    `created_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cms_page_generation_target` (`target_type`, `target_id`, `locale`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;
