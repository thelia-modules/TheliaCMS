-- Item blocks, added in 1.4.0: the one block of a catalogue item (a product,
-- a category, a brand, a store), held in `cms_item_block`.
--
-- Written to be replayable: the same file is applied by a fresh install (after
-- TheliaMain.sql, which already declares the table) and by an update of an
-- existing site, so the statement tolerates being run twice.

CREATE TABLE IF NOT EXISTS `cms_item_block`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `item_type` VARCHAR(20) NOT NULL,
    `item_id` INTEGER NOT NULL,
    `block_id` INTEGER NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `unq_cms_item_block_item` (`item_type`, `item_id`),
    UNIQUE INDEX `unq_cms_item_block_block` (`block_id`),
    CONSTRAINT `fk_cms_item_block_block`
        FOREIGN KEY (`block_id`)
        REFERENCES `cms_block` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET='utf8mb4' COLLATE='utf8mb4_general_ci' ROW_FORMAT=DYNAMIC;
