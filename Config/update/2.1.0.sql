-- 2.1.0: a catalogue item carries a document of its own, the way a page does
-- (docs/adr/0011): a product, a category, a content or a folder, in each
-- language, written in the back office from the edit screen of the item and
-- printed by the theme with cms_item_content().
--
-- Written to be replayable, like every update file: a fresh install runs it
-- after TheliaMain.sql, which already declares both tables.

CREATE TABLE IF NOT EXISTS `cms_item_content`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `item_type` VARCHAR(16) NOT NULL,
    `item_id` INTEGER NOT NULL,
    `locale` VARCHAR(5) NOT NULL,
    `draft_document` LONGTEXT,
    `draft_html` LONGTEXT,
    `published_document` LONGTEXT,
    `published_html` LONGTEXT,
    `published_at` TIMESTAMP NULL,
    `draft_updated_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_item_content_item_locale` (`item_type`, `item_id`, `locale`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;

CREATE TABLE IF NOT EXISTS `cms_item_revision`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `item_type` VARCHAR(16) NOT NULL,
    `item_id` INTEGER NOT NULL,
    `locale` VARCHAR(5) NOT NULL,
    `document` LONGTEXT,
    `html` LONGTEXT,
    `created_by` INTEGER,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cms_item_revision_item_locale` (`item_type`, `item_id`, `locale`)
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;
