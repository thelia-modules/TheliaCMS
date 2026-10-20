-- 2.0.0: the content of a page or of a block becomes a document (see
-- TheliaCMS\Content\Document), rendered to HTML on save and on publish. The
-- columns of the former editor go: the project it reloaded from and the
-- stylesheets it wrote.
--
-- A site coming from 1.x keeps its pages and blocks — tree, addresses, SEO,
-- tags, types — and loses their content: the markup of the former editor has
-- no document to be read back from, and serving it without one would leave
-- pages nobody can edit. Those pages are put back to draft, their revisions,
-- search entries and saved templates removed with them. The release notes say
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
    0 < @cms_page_content_from_1x,
    'DELETE FROM `cms_page_template`',
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

-- cms_block_content --------------------------------------------------------

SET @cms_block_content_from_1x := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_block_content' AND column_name = 'draft_project_data'
);

SET @cms_statement := IF(
    0 < @cms_block_content_from_1x,
    'UPDATE `cms_block_content` SET `draft_html` = NULL, `published_html` = NULL, `published_at` = NULL',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_block_content' AND column_name = 'draft_document'),
    'ALTER TABLE `cms_block_content` ADD COLUMN `draft_document` LONGTEXT AFTER `locale`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_block_content' AND column_name = 'published_document'),
    'ALTER TABLE `cms_block_content` ADD COLUMN `published_document` LONGTEXT AFTER `draft_html`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 < @cms_block_content_from_1x,
    'ALTER TABLE `cms_block_content` DROP COLUMN `draft_project_data`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 < (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_block_content' AND column_name = 'draft_css'),
    'ALTER TABLE `cms_block_content` DROP COLUMN `draft_css`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;

SET @cms_statement := IF(
    0 < (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cms_block_content' AND column_name = 'published_css'),
    'ALTER TABLE `cms_block_content` DROP COLUMN `published_css`',
    'DO 0'
);
PREPARE cms_prepared FROM @cms_statement;
EXECUTE cms_prepared;
DEALLOCATE PREPARE cms_prepared;
