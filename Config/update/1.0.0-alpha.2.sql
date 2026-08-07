-- The address segment of a page, added in 1.0.0-alpha.2.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already declares the column) and by an update of an
-- existing site, so every statement tolerates being run twice.
--
-- `ADD COLUMN IF NOT EXISTS` is deliberately not used: MariaDB understands it
-- and MySQL does not, and the module supports both.

SET @cms_slug_column_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_i18n' AND column_name = 'slug'
);

SET @cms_slug_add := IF(
    0 = @cms_slug_column_exists,
    'ALTER TABLE `cms_page_i18n` ADD COLUMN `slug` VARCHAR(255) DEFAULT NULL AFTER `title`',
    'DO 0'
);

PREPARE cms_slug_statement FROM @cms_slug_add;

EXECUTE cms_slug_statement;

DEALLOCATE PREPARE cms_slug_statement;

-- A site that already has pages keeps the addresses it answers on: the segment
-- is read back from the core rewriting table, which is where it has been living
-- so far. Only the address in use is read, since the rows kept behind a rename
-- are the 301s and carry the previous segment.
UPDATE `cms_page_i18n` `i`
SET `i`.`slug` = (
        SELECT SUBSTRING_INDEX(CONVERT(`r`.`url` USING utf8mb4), '/', -1)
        FROM `rewriting_url` `r`
        WHERE `r`.`view` = 'cmspage'
          AND `r`.`view_id` = CAST(`i`.`id` AS CHAR)
          AND `r`.`view_locale` = `i`.`locale`
          AND `r`.`redirected` IS NULL
        ORDER BY `r`.`id` DESC
        LIMIT 1
    )
WHERE `i`.`slug` IS NULL OR `i`.`slug` = '';
