-- Reusable blocks, added in 0.3.0.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already contains these tables) and by an update of an
-- existing site, so every statement tolerates being run twice.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `cms_block`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `deleted_at` TIMESTAMP NULL,
    `created_by` INTEGER,
    `updated_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_block_code` (`code`),
    INDEX `idx_cms_block_deleted_at` (`deleted_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cms_block_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `cms_block_i18n_fk_16ce41`
        FOREIGN KEY (`id`)
        REFERENCES `cms_block` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cms_block_content`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `block_id` INTEGER NOT NULL,
    `locale` VARCHAR(5) NOT NULL,
    `draft_project_data` LONGTEXT,
    `draft_html` LONGTEXT,
    `draft_css` LONGTEXT,
    `published_html` LONGTEXT,
    `published_css` LONGTEXT,
    `published_at` TIMESTAMP NULL,
    `draft_updated_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_block_content_block_locale` (`block_id`, `locale`),
    CONSTRAINT `fk_cms_block_content_block`
        FOREIGN KEY (`block_id`)
        REFERENCES `cms_block` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
