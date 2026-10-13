-- Tags, added in 1.3.0: the `cms_tag` table, and the one tag a page may carry
-- in `cms_page.tag_id`.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already declares the table and the column) and by an
-- update of an existing site, so every statement tolerates being run twice.
--
-- `ADD COLUMN IF NOT EXISTS` is deliberately not used: MariaDB understands it
-- and MySQL does not, and the module supports both.

CREATE TABLE IF NOT EXISTS `cms_tag`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_tag_name` (`name`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

SET @cms_tag_column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page' AND column_name = 'tag_id'
);

SET @cms_tag_column_add := IF(
    0 = @cms_tag_column_exists,
    'ALTER TABLE `cms_page` ADD COLUMN `tag_id` INTEGER DEFAULT NULL AFTER `image_id`, ADD INDEX `idx_cms_page_tag` (`tag_id`)',
    'DO 0'
);

PREPARE cms_tag_column_statement FROM @cms_tag_column_add;

EXECUTE cms_tag_column_statement;

DEALLOCATE PREPARE cms_tag_column_statement;

-- Deleting a tag detaches its pages: the constraint is what makes that true
-- for a row deleted by hand as well as from the back office.
SET @cms_tag_constraint_exists := (
    SELECT COUNT(*) FROM information_schema.table_constraints
    WHERE table_schema = DATABASE() AND table_name = 'cms_page' AND constraint_name = 'fk_cms_page_tag'
);

SET @cms_tag_constraint_add := IF(
    0 = @cms_tag_constraint_exists,
    'ALTER TABLE `cms_page` ADD CONSTRAINT `fk_cms_page_tag` FOREIGN KEY (`tag_id`) REFERENCES `cms_tag` (`id`) ON UPDATE RESTRICT ON DELETE SET NULL',
    'DO 0'
);

PREPARE cms_tag_constraint_statement FROM @cms_tag_constraint_add;

EXECUTE cms_tag_constraint_statement;

DEALLOCATE PREPARE cms_tag_constraint_statement;
