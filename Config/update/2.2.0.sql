-- 2.2.0: a page may keep a fixed address in a language, whatever its place
-- in the tree: `cms_page_i18n.fixed_url`.
--
-- Written to be replayable: a fresh install runs it after TheliaMain.sql, which
-- already declares the column. `ADD COLUMN IF NOT EXISTS` is not used: MariaDB
-- understands it and MySQL does not.

SET @cms_fixed_url_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_i18n' AND column_name = 'fixed_url'
);

SET @cms_fixed_url_add := IF(
    0 = @cms_fixed_url_exists,
    'ALTER TABLE `cms_page_i18n` ADD COLUMN `fixed_url` VARCHAR(255) DEFAULT NULL AFTER `slug`',
    'DO 0'
);

PREPARE cms_fixed_url_statement FROM @cms_fixed_url_add;

EXECUTE cms_fixed_url_statement;

DEALLOCATE PREPARE cms_fixed_url_statement;
