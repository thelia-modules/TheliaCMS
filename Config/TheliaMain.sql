
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- cms_page
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `cms_page`;

CREATE TABLE `cms_page`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `parent` INTEGER,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `visible` TINYINT(4) DEFAULT 1 NOT NULL,
    `layout` VARCHAR(20) DEFAULT 'default' NOT NULL,
    `publish_at` TIMESTAMP NULL,
    `unpublish_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    `created_by` INTEGER,
    `updated_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cms_page_parent_position` (`parent`, `position`),
    INDEX `idx_cms_page_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_cms_page_parent`
        FOREIGN KEY (`parent`)
        REFERENCES `cms_page` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- cms_page_content
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `cms_page_content`;

CREATE TABLE `cms_page_content`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `page_id` INTEGER NOT NULL,
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
    UNIQUE INDEX `unq_cms_page_content_page_locale` (`page_id`, `locale`),
    CONSTRAINT `fk_cms_page_content_page`
        FOREIGN KEY (`page_id`)
        REFERENCES `cms_page` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- cms_page_revision
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `cms_page_revision`;

CREATE TABLE `cms_page_revision`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `page_id` INTEGER NOT NULL,
    `locale` VARCHAR(5) NOT NULL,
    `project_data` LONGTEXT,
    `html` LONGTEXT,
    `css` LONGTEXT,
    `label` VARCHAR(60),
    `created_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cms_page_revision_page_locale` (`page_id`, `locale`),
    CONSTRAINT `fk_cms_page_revision_page`
        FOREIGN KEY (`page_id`)
        REFERENCES `cms_page` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- cms_page_search
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `cms_page_search`;

CREATE TABLE `cms_page_search`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `page_id` INTEGER NOT NULL,
    `locale` VARCHAR(5) NOT NULL,
    `content` LONGTEXT,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_page_search_page_locale` (`page_id`, `locale`),
    CONSTRAINT `fk_cms_page_search_page`
        FOREIGN KEY (`page_id`)
        REFERENCES `cms_page` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- cms_page_i18n
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `cms_page_i18n`;

CREATE TABLE `cms_page_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `meta_title` VARCHAR(255),
    `meta_description` TEXT,
    `og_title` VARCHAR(255),
    `og_description` TEXT,
    `og_image_id` INTEGER,
    `twitter_card` VARCHAR(20),
    `canonical` VARCHAR(2048),
    `noindex` TINYINT(4) DEFAULT 0 NOT NULL,
    `nofollow` TINYINT(4) DEFAULT 0 NOT NULL,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `cms_page_i18n_fk_058391`
        FOREIGN KEY (`id`)
        REFERENCES `cms_page` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
