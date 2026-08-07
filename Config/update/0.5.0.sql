-- Third-party scripts and their consent category, added in 0.5.0.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already contains this table) and by an update of an
-- existing site, so every statement tolerates being run twice.

CREATE TABLE IF NOT EXISTS `cms_script`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(100) NOT NULL,
    `placement` VARCHAR(20) DEFAULT 'head' NOT NULL,
    `consent_category` VARCHAR(50),
    `active` TINYINT(4) DEFAULT 0 NOT NULL,
    `content` LONGTEXT,
    `note` VARCHAR(500),
    `created_by` INTEGER,
    `updated_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cms_script_active_placement` (`active`, `placement`)
) ENGINE=InnoDB;
