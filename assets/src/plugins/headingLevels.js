/**
 * Keeps the blocks that come from third-party presets from opening on a level 1
 * heading.
 *
 * The "Text section" block of the webpage preset starts with an `<h1>`. Dropped
 * under the title of a page, which is itself an `<h1>`, it gives two competing
 * level 1 headings: the publication check then reports a problem the author did
 * nothing to cause, and the way to fix it is buried in the settings of the
 * heading. The blocks are left as they are apart from the level they start at,
 * which the author can still change from the Tag setting of the heading.
 */
export default (editor) => {
    ["text-basic"].forEach((id) => {
        const block = editor.BlockManager.get(id);
        const content = block?.get("content");

        if (typeof content !== "string") {
            return;
        }

        block.set("content", content.replace(/<(\/?)h1\b/gi, "<$1h2"));
    });
};
