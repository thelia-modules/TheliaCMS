-- Storage settings of the tables, aligned on the core ones, added in 1.0.0-alpha.6.
--
-- Until now the schema declared no charset, so every table took the default of
-- the server it was installed on. On a host whose default is not utf8mb4 that
-- costs two things: a join between a table of the module and a table of the core
-- is refused outright (error 1267, illegal mix of collations), and any character
-- the default charset does not cover is truncated on the way in.
--
-- Each table is converted only when its collation differs, so the file is
-- replayable and a site already in utf8mb4 pays nothing. DYNAMIC comes first in
-- the same statement: it raises the index prefix limit to 3072 bytes, which the
-- VARCHAR(255) indexes need once a character can take four bytes.


SET @cms_convert_page := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_page'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_page,
    'ALTER TABLE `cms_page` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_page_statement FROM @cms_convert_statement;

EXECUTE cms_convert_page_statement;

DEALLOCATE PREPARE cms_convert_page_statement;

SET @cms_convert_page_content := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_page_content'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_page_content,
    'ALTER TABLE `cms_page_content` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_page_content_statement FROM @cms_convert_statement;

EXECUTE cms_convert_page_content_statement;

DEALLOCATE PREPARE cms_convert_page_content_statement;

SET @cms_convert_page_revision := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_page_revision'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_page_revision,
    'ALTER TABLE `cms_page_revision` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_page_revision_statement FROM @cms_convert_statement;

EXECUTE cms_convert_page_revision_statement;

DEALLOCATE PREPARE cms_convert_page_revision_statement;

SET @cms_convert_page_search := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_page_search'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_page_search,
    'ALTER TABLE `cms_page_search` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_page_search_statement FROM @cms_convert_statement;

EXECUTE cms_convert_page_search_statement;

DEALLOCATE PREPARE cms_convert_page_search_statement;

SET @cms_convert_menu := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_menu'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_menu,
    'ALTER TABLE `cms_menu` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_menu_statement FROM @cms_convert_statement;

EXECUTE cms_convert_menu_statement;

DEALLOCATE PREPARE cms_convert_menu_statement;

SET @cms_convert_menu_item := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_menu_item'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_menu_item,
    'ALTER TABLE `cms_menu_item` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_menu_item_statement FROM @cms_convert_statement;

EXECUTE cms_convert_menu_item_statement;

DEALLOCATE PREPARE cms_convert_menu_item_statement;

SET @cms_convert_block := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_block'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_block,
    'ALTER TABLE `cms_block` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_block_statement FROM @cms_convert_statement;

EXECUTE cms_convert_block_statement;

DEALLOCATE PREPARE cms_convert_block_statement;

SET @cms_convert_block_content := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_block_content'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_block_content,
    'ALTER TABLE `cms_block_content` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_block_content_statement FROM @cms_convert_statement;

EXECUTE cms_convert_block_content_statement;

DEALLOCATE PREPARE cms_convert_block_content_statement;

SET @cms_convert_form := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_form'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_form,
    'ALTER TABLE `cms_form` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_form_statement FROM @cms_convert_statement;

EXECUTE cms_convert_form_statement;

DEALLOCATE PREPARE cms_convert_form_statement;

SET @cms_convert_form_field := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_form_field'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_form_field,
    'ALTER TABLE `cms_form_field` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_form_field_statement FROM @cms_convert_statement;

EXECUTE cms_convert_form_field_statement;

DEALLOCATE PREPARE cms_convert_form_field_statement;

SET @cms_convert_form_submission := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_form_submission'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_form_submission,
    'ALTER TABLE `cms_form_submission` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_form_submission_statement FROM @cms_convert_statement;

EXECUTE cms_convert_form_submission_statement;

DEALLOCATE PREPARE cms_convert_form_submission_statement;

SET @cms_convert_script := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_script'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_script,
    'ALTER TABLE `cms_script` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_script_statement FROM @cms_convert_statement;

EXECUTE cms_convert_script_statement;

DEALLOCATE PREPARE cms_convert_script_statement;

SET @cms_convert_page_template := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_page_template'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_page_template,
    'ALTER TABLE `cms_page_template` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_page_template_statement FROM @cms_convert_statement;

EXECUTE cms_convert_page_template_statement;

DEALLOCATE PREPARE cms_convert_page_template_statement;

SET @cms_convert_page_i18n := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_page_i18n'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_page_i18n,
    'ALTER TABLE `cms_page_i18n` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_page_i18n_statement FROM @cms_convert_statement;

EXECUTE cms_convert_page_i18n_statement;

DEALLOCATE PREPARE cms_convert_page_i18n_statement;

SET @cms_convert_menu_i18n := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_menu_i18n'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_menu_i18n,
    'ALTER TABLE `cms_menu_i18n` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_menu_i18n_statement FROM @cms_convert_statement;

EXECUTE cms_convert_menu_i18n_statement;

DEALLOCATE PREPARE cms_convert_menu_i18n_statement;

SET @cms_convert_menu_item_i18n := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_menu_item_i18n'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_menu_item_i18n,
    'ALTER TABLE `cms_menu_item_i18n` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_menu_item_i18n_statement FROM @cms_convert_statement;

EXECUTE cms_convert_menu_item_i18n_statement;

DEALLOCATE PREPARE cms_convert_menu_item_i18n_statement;

SET @cms_convert_block_i18n := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_block_i18n'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_block_i18n,
    'ALTER TABLE `cms_block_i18n` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_block_i18n_statement FROM @cms_convert_statement;

EXECUTE cms_convert_block_i18n_statement;

DEALLOCATE PREPARE cms_convert_block_i18n_statement;

SET @cms_convert_form_i18n := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_form_i18n'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_form_i18n,
    'ALTER TABLE `cms_form_i18n` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_form_i18n_statement FROM @cms_convert_statement;

EXECUTE cms_convert_form_i18n_statement;

DEALLOCATE PREPARE cms_convert_form_i18n_statement;

SET @cms_convert_form_field_i18n := (
    SELECT COUNT(*) = 1 FROM `information_schema`.`TABLES`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'cms_form_field_i18n'
      AND `TABLE_COLLATION` <> 'utf8mb4_general_ci'
);

SET @cms_convert_statement := IF(
    @cms_convert_form_field_i18n,
    'ALTER TABLE `cms_form_field_i18n` ROW_FORMAT=DYNAMIC, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci',
    'DO 0'
);

PREPARE cms_convert_form_field_i18n_statement FROM @cms_convert_statement;

EXECUTE cms_convert_form_field_i18n_statement;

DEALLOCATE PREPARE cms_convert_form_field_i18n_statement;
