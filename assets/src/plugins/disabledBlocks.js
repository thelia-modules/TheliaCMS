/**
 * Takes the blocks switched off under CMS > Settings out of the panel.
 *
 * The catalogue and the dynamic blocks are handled by the server, which hands
 * the editor a filtered list. The blocks of the bundle and of the GrapesJS
 * presets are registered by their own plugins, in the editor, so they are
 * removed here, once every plugin has added its own — which is why this plugin
 * comes last. Removing an id nobody registered does nothing.
 *
 * Options: `ids`, the identifiers the blocks were registered under.
 */
export default (editor, options = {}) => {
    const { ids = [] } = options;
    const remove = () => ids.forEach((id) => editor.Blocks.remove(id));

    remove();
    // Some plugins touch their blocks again once the editor is loaded.
    editor.on("load", remove);
};
