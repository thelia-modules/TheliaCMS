-- Page templates, added in 0.6.0.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already contains this table) and by an update of an
-- existing site, so every statement tolerates being run twice.

CREATE TABLE IF NOT EXISTS `cms_page_template`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` VARCHAR(500),
    `payload` LONGTEXT NOT NULL,
    `created_by` INTEGER,
    `updated_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_page_template_code` (`code`)
) ENGINE=InnoDB;
