-- Page types, added in 1.2.0: the `layout` of a page becomes its `page_type`,
-- the code of a row of `cms_page_type`.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already declares the table and the column) and by an
-- update of an existing site, so every statement tolerates being run twice.
--
-- The column is renamed with CHANGE COLUMN: RENAME COLUMN needs MySQL 8 or
-- MariaDB 10.5, and the module supports older servers.

CREATE TABLE IF NOT EXISTS `cms_page_type`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_page_type_code` (`code`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

SET @cms_layout_column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page' AND column_name = 'layout'
);

SET @cms_layout_rename := IF(
    0 < @cms_layout_column_exists,
    'ALTER TABLE `cms_page` CHANGE COLUMN `layout` `page_type` VARCHAR(50) NOT NULL DEFAULT ''default''',
    'DO 0'
);

PREPARE cms_layout_statement FROM @cms_layout_rename;

EXECUTE cms_layout_statement;

DEALLOCATE PREPARE cms_layout_statement;

-- The code ends up in a template name: a value the page form never allowed
-- but a hand-written query did is the default type, not a type. Written as the
-- characters a code may not hold rather than as an anchored pattern: `$`
-- also matches before a trailing line break, and which line breaks depends on
-- the regular expression engine of the server. Compared under a binary
-- collation, the column one ignores case.
UPDATE `cms_page` SET `page_type` = 'default'
WHERE `page_type` = ''
   OR `page_type` COLLATE utf8mb4_bin REGEXP '[^a-z0-9-]|^-|-$|--';

-- The former layouts, so a page keeps the one it had, and whatever else the
-- pages already carry.
INSERT IGNORE INTO `cms_page_type` (`code`, `created_at`, `updated_at`)
    SELECT 'default', NOW(), NOW() FROM DUAL
    UNION SELECT 'full-width', NOW(), NOW() FROM DUAL
    UNION SELECT 'landing', NOW(), NOW() FROM DUAL
    UNION SELECT DISTINCT `page_type`, NOW(), NOW() FROM `cms_page`;
