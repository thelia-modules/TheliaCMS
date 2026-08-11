/**
 * A writing area in the settings panel for the blocks that hold editorial
 * text.
 *
 * The canvas already edits text in place, but nothing in the panel says so,
 * and a person coming from any other CMS looks for their text on the right.
 * Every block whose content is text gets a `Content` setting: the same words,
 * editable in both places, each side following the other.
 *
 * No editor library is bundled for this: the area runs on the same
 * document.execCommand the rich-text toolbar of the canvas runs on, and the
 * published page goes through the server-side sanitizer either way.
 */
export default (editor, options = {}) => {
    const labels = options.labels ?? {};

    const ACTIONS = [
        { command: "bold", glyph: "<b>B</b>", title: labels.bold },
        { command: "italic", glyph: "<i>I</i>", title: labels.italic },
        { command: "underline", glyph: "<u>U</u>", title: labels.underline },
        { command: "strikeThrough", glyph: "<s>S</s>", title: labels.strikethrough },
        { command: "insertUnorderedList", glyph: "•", title: labels.bulletList },
        { command: "insertOrderedList", glyph: "1.", title: labels.numberedList },
    ];

    editor.TraitManager.addType("cms-rich-text", {
        // The trait edits the content of the block, not one of its attributes.
        createInput({ component }) {
            const wrap = document.createElement("div");
            wrap.className = "cms-rte";

            const bar = document.createElement("div");
            bar.className = "cms-rte__bar";

            for (const action of ACTIONS) {
                const button = document.createElement("button");
                button.type = "button";
                button.className = "cms-rte__action";
                button.dataset.command = action.command;
                button.innerHTML = action.glyph;

                if (action.title) {
                    button.title = action.title;
                    button.setAttribute("aria-label", action.title);
                }

                bar.append(button);
            }

            const area = document.createElement("div");
            area.className = "cms-rte__area";
            area.contentEditable = "true";
            area.innerHTML = component.getInnerHTML();

            bar.addEventListener("click", (event) => {
                const button = event.target.closest("button");

                if (!button) {
                    return;
                }

                // The click must not take the focus, and the change it makes
                // has to travel the same road as typing.
                event.preventDefault();
                area.focus();
                document.execCommand(button.dataset.command);
                area.dispatchEvent(new Event("input", { bubbles: true }));
            });

            // Written in the canvas while the panel is open: the area follows,
            // unless the person is writing in the area itself. The view of a
            // trait is rebuilt on every selection, so the listener lets go of
            // the editor as soon as its area has left the panel.
            const follow = (changed) => {
                if (!wrap.isConnected) {
                    editor.off("component:update", follow);

                    return;
                }

                if (changed === component && !wrap.contains(document.activeElement)) {
                    area.innerHTML = component.getInnerHTML();
                }
            };

            editor.on("component:update", follow);

            wrap.append(bar, area);

            return wrap;
        },

        onEvent({ elInput, component }) {
            const area = elInput.querySelector(".cms-rte__area");

            component.components(area.innerHTML);
        },

        eventCapture: ["input"],
    });

    // Every block whose content is text gets the writing area: paragraphs and
    // quotes are `text`, headings are `title`. Appended after whatever
    // settings the type already offers — the heading keeps its level select.
    for (const type of ["text", "title"]) {
        const current = editor.DomComponents.getType(type);

        if (!current) {
            continue;
        }

        const traits = current.model.prototype.defaults.traits ?? ["id", "title"];

        editor.DomComponents.addType(type, {
            model: {
                defaults: {
                    traits: [
                        ...traits,
                        { type: "cms-rich-text", name: "cms-content", label: labels.label ?? "Content" },
                    ],
                },
            },
        });
    }
};
