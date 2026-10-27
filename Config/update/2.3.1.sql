-- 2.3.1: a revision of a catalogue item says what it is, as a revision of a
-- page does: `cms_item_revision.label`, "draft" for the draft a generation
-- replaced, empty for a publication.
--
-- Written to be replayable: a fresh install runs it after TheliaMain.sql,
-- which already has the column. `ADD COLUMN IF NOT EXISTS` is not used:
-- MariaDB understands it and MySQL does not.

SET @cms_item_revision_label_exists := (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'cms_item_revision' AND column_name = 'label'
);

SET @cms_item_revision_label_add := IF(
    0 = @cms_item_revision_label_exists,
    'ALTER TABLE `cms_item_revision` ADD COLUMN `label` VARCHAR(60) DEFAULT NULL AFTER `html`',
    'DO 0'
);

PREPARE cms_item_revision_label_statement FROM @cms_item_revision_label_add;

EXECUTE cms_item_revision_label_statement;

DEALLOCATE PREPARE cms_item_revision_label_statement;
