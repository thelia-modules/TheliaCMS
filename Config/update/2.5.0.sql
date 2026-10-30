-- 2.5.0: a tag may have a color, the name of an entry of the palette
-- (TheliaCMS\Page\PageTagColor). The tags of a site that updates keep no color
-- and show as before.
--
-- Written to be replayable: a fresh install runs it after TheliaMain.sql, which
-- already has the column. `ADD COLUMN IF NOT EXISTS` is not used: MariaDB
-- understands it and MySQL does not.

SET @cms_tag_color_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_tag' AND column_name = 'color'
);

SET @cms_tag_color_add := IF(
    0 = @cms_tag_color_exists,
    'ALTER TABLE `cms_tag` ADD COLUMN `color` VARCHAR(16) DEFAULT NULL AFTER `name`',
    'DO 0'
);

PREPARE cms_tag_color_statement FROM @cms_tag_color_add;

EXECUTE cms_tag_color_statement;

DEALLOCATE PREPARE cms_tag_color_statement;
