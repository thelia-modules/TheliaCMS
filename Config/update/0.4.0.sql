-- Forms, their fields and their submissions, added in 0.4.0.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already contains these tables) and by an update of an
-- existing site, so every statement tolerates being run twice.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `cms_form`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `active` TINYINT(4) DEFAULT 1 NOT NULL,
    `recipients` VARCHAR(1024),
    `store_submissions` TINYINT(4) DEFAULT 1 NOT NULL,
    `retention_days` INTEGER DEFAULT 365 NOT NULL,
    `send_receipt` TINYINT(4) DEFAULT 0 NOT NULL,
    `privacy_policy_page_id` INTEGER,
    `lead_event` TINYINT(4) DEFAULT 1 NOT NULL,
    `deleted_at` TIMESTAMP NULL,
    `created_by` INTEGER,
    `updated_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_form_code` (`code`),
    INDEX `idx_cms_form_deleted_at` (`deleted_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cms_form_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `title` VARCHAR(255),
    `description` TEXT,
    `submit_label` VARCHAR(80),
    `success_message` TEXT,
    `legal_notice` TEXT,
    `receipt_subject` VARCHAR(255),
    `receipt_body` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `cms_form_i18n_fk_42eeb1`
        FOREIGN KEY (`id`)
        REFERENCES `cms_form` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cms_form_field`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `form_id` INTEGER NOT NULL,
    `position` INTEGER DEFAULT 0 NOT NULL,
    `type` VARCHAR(20) DEFAULT 'text' NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `required` TINYINT(4) DEFAULT 0 NOT NULL,
    `options` LONGTEXT,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_form_field_form_name` (`form_id`, `name`),
    INDEX `idx_cms_form_field_form_position` (`form_id`, `position`),
    CONSTRAINT `fk_cms_form_field_form`
        FOREIGN KEY (`form_id`)
        REFERENCES `cms_form` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cms_form_field_i18n`
(
    `id` INTEGER NOT NULL,
    `locale` VARCHAR(5) DEFAULT 'en_US' NOT NULL,
    `label` VARCHAR(255),
    `placeholder` VARCHAR(255),
    `help` TEXT,
    `choices` TEXT,
    PRIMARY KEY (`id`,`locale`),
    CONSTRAINT `cms_form_field_i18n_fk_0aecbb`
        FOREIGN KEY (`id`)
        REFERENCES `cms_form_field` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cms_form_submission`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `form_id` INTEGER NOT NULL,
    `locale` VARCHAR(5) NOT NULL,
    `email` VARCHAR(255),
    `data` LONGTEXT,
    `ip_hash` VARCHAR(64),
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cms_form_submission_form_created` (`form_id`, `created_at`),
    INDEX `idx_cms_form_submission_email` (`email`),
    CONSTRAINT `fk_cms_form_submission_form`
        FOREIGN KEY (`form_id`)
        REFERENCES `cms_form` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- A contact form to start from, with the four fields every site asks for and
-- the consent box that makes a phone number usable for a commercial call.
--
-- Seeded here rather than from PHP: the Propel runtime map of the process
-- running an update was built before the statements above, so the tables they
-- create have no model to write through yet.
INSERT INTO `cms_form` (`code`, `active`, `store_submissions`, `retention_days`, `send_receipt`, `lead_event`)
SELECT 'contact', 1, 1, 365, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM `cms_form` WHERE `code` = 'contact');

INSERT INTO `cms_form_i18n` (`id`, `locale`, `title`, `submit_label`, `success_message`, `legal_notice`)
    SELECT `f`.`id`, `l`.`locale`,
           CASE WHEN `l`.`locale` = 'fr_FR' THEN 'Nous contacter' ELSE 'Contact us' END,
           CASE WHEN `l`.`locale` = 'fr_FR' THEN 'Envoyer' ELSE 'Send' END,
           CASE WHEN `l`.`locale` = 'fr_FR'
                THEN 'Merci, votre message est parti. Nous vous répondons rapidement.'
                ELSE 'Thank you, your message has been sent. We will get back to you shortly.' END,
           CASE WHEN `l`.`locale` = 'fr_FR'
                THEN 'Les informations envoyées ici servent uniquement à répondre à votre demande. Elles sont conservées le temps nécessaire pour cela et ne sont transmises à personne. Vous pouvez demander à les consulter, à les corriger ou à les supprimer.'
                ELSE 'What you send here is used only to answer your request. It is kept for as long as that takes and passed on to nobody. You can ask to see it, to correct it or to have it deleted.' END
    FROM `cms_form` `f`
    JOIN `lang` `l` ON `l`.`active` = 1
    WHERE `f`.`code` = 'contact'
      AND NOT EXISTS (SELECT 1 FROM `cms_form_i18n` `i` WHERE `i`.`id` = `f`.`id` AND `i`.`locale` = `l`.`locale`);

INSERT INTO `cms_form_field` (`form_id`, `position`, `type`, `name`, `required`)
SELECT f.`id`, 1, 'text', 'name', 1 FROM `cms_form` f
WHERE f.`code` = 'contact'
  AND NOT EXISTS (SELECT 1 FROM `cms_form_field` x WHERE x.`form_id` = f.`id` AND x.`name` = 'name');

INSERT INTO `cms_form_field` (`form_id`, `position`, `type`, `name`, `required`)
SELECT f.`id`, 2, 'email', 'email', 1 FROM `cms_form` f
WHERE f.`code` = 'contact'
  AND NOT EXISTS (SELECT 1 FROM `cms_form_field` x WHERE x.`form_id` = f.`id` AND x.`name` = 'email');

INSERT INTO `cms_form_field` (`form_id`, `position`, `type`, `name`, `required`)
SELECT f.`id`, 3, 'phone', 'phone', 0 FROM `cms_form` f
WHERE f.`code` = 'contact'
  AND NOT EXISTS (SELECT 1 FROM `cms_form_field` x WHERE x.`form_id` = f.`id` AND x.`name` = 'phone');

INSERT INTO `cms_form_field` (`form_id`, `position`, `type`, `name`, `required`)
SELECT f.`id`, 4, 'textarea', 'message', 1 FROM `cms_form` f
WHERE f.`code` = 'contact'
  AND NOT EXISTS (SELECT 1 FROM `cms_form_field` x WHERE x.`form_id` = f.`id` AND x.`name` = 'message');

INSERT INTO `cms_form_field` (`form_id`, `position`, `type`, `name`, `required`)
SELECT f.`id`, 5, 'consent', 'consent', 1 FROM `cms_form` f
WHERE f.`code` = 'contact'
  AND NOT EXISTS (SELECT 1 FROM `cms_form_field` x WHERE x.`form_id` = f.`id` AND x.`name` = 'consent');

INSERT INTO `cms_form_field_i18n` (`id`, `locale`, `label`)
    SELECT `x`.`id`, `l`.`locale`,
           CASE WHEN `l`.`locale` = 'fr_FR' THEN
               CASE `x`.`name`
                   WHEN 'name' THEN 'Votre nom'
                   WHEN 'email' THEN 'Votre adresse e-mail'
                   WHEN 'phone' THEN 'Votre numéro de téléphone'
                   WHEN 'message' THEN 'Votre message'
                   ELSE 'J’accepte d’être recontacté au sujet de ma demande.'
               END
           ELSE
               CASE `x`.`name`
                   WHEN 'name' THEN 'Your name'
                   WHEN 'email' THEN 'Your email address'
                   WHEN 'phone' THEN 'Your phone number'
                   WHEN 'message' THEN 'Your message'
                   ELSE 'I agree to be contacted about my request.'
               END
           END
    FROM `cms_form_field` `x`
    JOIN `cms_form` `f` ON `f`.`id` = `x`.`form_id`
    JOIN `lang` `l` ON `l`.`active` = 1
    WHERE `f`.`code` = 'contact'
      AND NOT EXISTS (SELECT 1 FROM `cms_form_field_i18n` `i` WHERE `i`.`id` = `x`.`id` AND `i`.`locale` = `l`.`locale`);

SET FOREIGN_KEY_CHECKS = 1;
