-- 2.4.0: a language written from another one remembers which, and the
-- fingerprint of the draft it was written from, so the back office can say
-- when that draft changed since: `translated_from` and `source_fingerprint`
-- on `cms_page_content` and `cms_item_content`.
--
-- Written to be replayable: a fresh install runs it after TheliaMain.sql,
-- which already has the columns. `ADD COLUMN IF NOT EXISTS` is not used:
-- MariaDB understands it and MySQL does not.

SET @cms_page_content_translated_from_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_page_content' AND column_name = 'translated_from'
);

SET @cms_page_content_translated_from_add := IF(
    0 = @cms_page_content_translated_from_exists,
    'ALTER TABLE `cms_page_content` ADD COLUMN `translated_from` VARCHAR(5) DEFAULT NULL AFTER `draft_updated_by`, ADD COLUMN `source_fingerprint` CHAR(64) DEFAULT NULL AFTER `translated_from`',
    'DO 0'
);

PREPARE cms_page_content_translated_from_statement FROM @cms_page_content_translated_from_add;

EXECUTE cms_page_content_translated_from_statement;

DEALLOCATE PREPARE cms_page_content_translated_from_statement;

SET @cms_item_content_translated_from_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_item_content' AND column_name = 'translated_from'
);

SET @cms_item_content_translated_from_add := IF(
    0 = @cms_item_content_translated_from_exists,
    'ALTER TABLE `cms_item_content` ADD COLUMN `translated_from` VARCHAR(5) DEFAULT NULL AFTER `draft_updated_by`, ADD COLUMN `source_fingerprint` CHAR(64) DEFAULT NULL AFTER `translated_from`',
    'DO 0'
);

PREPARE cms_item_content_translated_from_statement FROM @cms_item_content_translated_from_add;

EXECUTE cms_item_content_translated_from_statement;

DEALLOCATE PREPARE cms_item_content_translated_from_statement;
