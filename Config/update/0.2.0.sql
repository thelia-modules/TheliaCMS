-- Menus, added in 0.2.0.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already contains these tables) and by an update of an
-- existing site, so every statement tolerates being run twice.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `cms_menu`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_menu_code` (`code`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cms_menu_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `cms_menu_i18n_fk_05b691`
        FOREIGN KEY (`id`)
        REFERENCES `cms_menu` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cms_menu_item`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `menu_id` INTEGER NOT NULL,
    `parent` INTEGER DEFAULT 0 NOT NULL,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `target_type` VARCHAR(20) DEFAULT 'page' NOT NULL,
    `target_id` INTEGER,
    `url` VARCHAR(2048),
    `open_new_tab` TINYINT(4) DEFAULT 0 NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cms_menu_item_menu_parent_position` (`menu_id`, `parent`, `position`),
    INDEX `idx_cms_menu_item_target` (`target_type`, `target_id`),
    CONSTRAINT `fk_cms_menu_item_menu`
        FOREIGN KEY (`menu_id`)
        REFERENCES `cms_menu` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cms_menu_item_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `label` VARCHAR(255),
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `cms_menu_item_i18n_fk_ea832f`
        FOREIGN KEY (`id`)
        REFERENCES `cms_menu_item` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- The two menus every theme calls by code. Seeded in SQL rather than in PHP
-- because this file also runs while a site is being updated, at a point where
-- the tables it just created have no Propel table map in the running process.
INSERT INTO `cms_menu` (`code`, `created_at`, `updated_at`)
    SELECT 'main', NOW(), NOW() FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `cms_menu` WHERE `code` = 'main');

INSERT INTO `cms_menu` (`code`, `created_at`, `updated_at`)
    SELECT 'footer', NOW(), NOW() FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM `cms_menu` WHERE `code` = 'footer');

INSERT INTO `cms_menu_i18n` (`id`, `locale`, `title`)
    SELECT `m`.`id`, `l`.`locale`,
           CASE WHEN `l`.`locale` = 'fr_FR' THEN 'Menu principal' ELSE 'Main menu' END
    FROM `cms_menu` `m`
    JOIN `lang` `l` ON `l`.`active` = 1
    WHERE `m`.`code` = 'main'
      AND NOT EXISTS (SELECT 1 FROM `cms_menu_i18n` `i` WHERE `i`.`id` = `m`.`id` AND `i`.`locale` = `l`.`locale`);

INSERT INTO `cms_menu_i18n` (`id`, `locale`, `title`)
    SELECT `m`.`id`, `l`.`locale`,
           CASE WHEN `l`.`locale` = 'fr_FR' THEN 'Menu du pied de page' ELSE 'Footer menu' END
    FROM `cms_menu` `m`
    JOIN `lang` `l` ON `l`.`active` = 1
    WHERE `m`.`code` = 'footer'
      AND NOT EXISTS (SELECT 1 FROM `cms_menu_i18n` `i` WHERE `i`.`id` = `m`.`id` AND `i`.`locale` = `l`.`locale`);

SET FOREIGN_KEY_CHECKS = 1;
