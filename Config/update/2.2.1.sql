-- 2.2.1: a page type carries the writing instructions of its pages:
-- `cms_page_type.instructions`. First shipped in 2.2.0.sql, which a site
-- already on 2.2.0 never plays again: it moved here so that such a site gets
-- the column too.
--
-- Written to be replayable: a fresh install runs it after TheliaMain.sql, and
-- a site that played the former 2.2.0.sql already has the column.
-- `ADD COLUMN IF NOT EXISTS` is not used: MariaDB understands it and MySQL
-- does not.

SET @cms_type_instructions_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_type' AND column_name = 'instructions'
);

SET @cms_type_instructions_add := IF(
    0 = @cms_type_instructions_exists,
    'ALTER TABLE `cms_page_type` ADD COLUMN `instructions` LONGTEXT DEFAULT NULL AFTER `brief_audience`',
    'DO 0'
);

PREPARE cms_type_instructions_statement FROM @cms_type_instructions_add;

EXECUTE cms_type_instructions_statement;

DEALLOCATE PREPARE cms_type_instructions_statement;
