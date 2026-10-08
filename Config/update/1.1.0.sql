-- The summary, the description and the image of a page, added in 1.1.0.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already declares the columns) and by an update of an
-- existing site, so every statement tolerates being run twice.
--
-- `ADD COLUMN IF NOT EXISTS` is deliberately not used: MariaDB understands it
-- and MySQL does not, and the module supports both.

SET @cms_chapo_column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_i18n' AND column_name = 'chapo'
);

SET @cms_chapo_add := IF(
    0 = @cms_chapo_column_exists,
    'ALTER TABLE `cms_page_i18n` ADD COLUMN `chapo` TEXT DEFAULT NULL AFTER `slug`',
    'DO 0'
);

PREPARE cms_chapo_statement FROM @cms_chapo_add;

EXECUTE cms_chapo_statement;

DEALLOCATE PREPARE cms_chapo_statement;

SET @cms_description_column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_i18n' AND column_name = 'description'
);

SET @cms_description_add := IF(
    0 = @cms_description_column_exists,
    'ALTER TABLE `cms_page_i18n` ADD COLUMN `description` LONGTEXT DEFAULT NULL AFTER `chapo`',
    'DO 0'
);

PREPARE cms_description_statement FROM @cms_description_add;

EXECUTE cms_description_statement;

DEALLOCATE PREPARE cms_description_statement;

SET @cms_image_column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page' AND column_name = 'image_id'
);

SET @cms_image_add := IF(
    0 = @cms_image_column_exists,
    'ALTER TABLE `cms_page` ADD COLUMN `image_id` INTEGER DEFAULT NULL AFTER `layout`',
    'DO 0'
);

PREPARE cms_image_statement FROM @cms_image_add;

EXECUTE cms_image_statement;

DEALLOCATE PREPARE cms_image_statement;
