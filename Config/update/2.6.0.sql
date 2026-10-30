-- 2.6.0: a language may have a menu of its own. `cms_menu.menu_locale` is
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
