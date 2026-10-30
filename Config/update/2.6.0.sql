-- 2.6.0: the files a page offers to download, with a title and a description
-- in each language. The file is the same in every language; it lives under
-- `local/media/documents/cms-page/` and one row owns one file.
--
-- Written to be replayable, like every update file: a fresh install runs it
-- after TheliaMain.sql, which already declares both tables.

CREATE TABLE IF NOT EXISTS `cms_page_document`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `page_id` INTEGER NOT NULL,
    `file` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(127) NOT NULL,
    `size` INTEGER NOT NULL,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_page_document_file` (`file`),
    INDEX `idx_cms_page_document_page_position` (`page_id`, `position`),
    CONSTRAINT `fk_cms_page_document_page`
        FOREIGN KEY (`page_id`)
        REFERENCES `cms_page` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `cms_page_document_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `cms_page_document_i18n_fk_44bfb8`
        FOREIGN KEY (`id`)
        REFERENCES `cms_page_document` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

-- And a language may have a menu of its own. `cms_menu.menu_locale` is
-- empty on the shared menu of a code and holds the locale on a detached one,
-- and the unique index moves from `code` to (`code`, `menu_locale`).
--
-- Written to be replayable: a fresh install runs it after TheliaMain.sql, which
-- already declares the column and the new index. `ADD COLUMN IF NOT EXISTS` is
-- not used: MariaDB understands it and MySQL does not. The new index is created
-- before the old one is dropped, so the codes are never left without one.

SET @cms_menu_locale_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_menu' AND column_name = 'menu_locale'
);

SET @cms_menu_locale_add := IF(
    0 = @cms_menu_locale_exists,
    'ALTER TABLE `cms_menu` ADD COLUMN `menu_locale` VARCHAR(5) DEFAULT \'\' NOT NULL AFTER `code`',
    'DO 0'
);

PREPARE cms_menu_locale_statement FROM @cms_menu_locale_add;

EXECUTE cms_menu_locale_statement;

DEALLOCATE PREPARE cms_menu_locale_statement;

SET @cms_menu_code_locale_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'cms_menu' AND index_name = 'unq_cms_menu_code_locale'
);

SET @cms_menu_code_locale_add := IF(
    0 = @cms_menu_code_locale_exists,
    'ALTER TABLE `cms_menu` ADD UNIQUE INDEX `unq_cms_menu_code_locale` (`code`, `menu_locale`)',
    'DO 0'
);

PREPARE cms_menu_code_locale_statement FROM @cms_menu_code_locale_add;

EXECUTE cms_menu_code_locale_statement;

DEALLOCATE PREPARE cms_menu_code_locale_statement;

SET @cms_menu_code_exists := (
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE() AND table_name = 'cms_menu' AND index_name = 'unq_cms_menu_code'
);

SET @cms_menu_code_drop := IF(
    0 < @cms_menu_code_exists,
    'ALTER TABLE `cms_menu` DROP INDEX `unq_cms_menu_code`',
    'DO 0'
);

PREPARE cms_menu_code_statement FROM @cms_menu_code_drop;

EXECUTE cms_menu_code_statement;

DEALLOCATE PREPARE cms_menu_code_statement;
