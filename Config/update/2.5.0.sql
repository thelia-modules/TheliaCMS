-- 2.5.0: the files a page offers to download, with a title and a description
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
