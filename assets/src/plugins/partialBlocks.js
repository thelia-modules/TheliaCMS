const TYPE = "cms-partial";
const NAME_ATTRIBUTE = "data-cms-partial";
const PROPS_ATTRIBUTE = "data-props";

const ICON =
    '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M4 5h16v3H4zm0 5h10v9H4zm12 0h4v4h-4zm0 5h4v4h-4z"/></svg>';

/**
 * Editing chrome for the previews, injected into the canvas document.
 *
 * It is added to the `<head>` of the canvas rather than to the stylesheet of
 * the page: the CssComposer is what gets published, and none of this belongs on
 * the live site.
 */
const CANVAS_CSS = `
[data-cms-partial] {
    position: relative;
    outline: 1px dashed #6d28d9;
    outline-offset: 2px;
    min-height: 2.5rem;
}
[data-cms-partial]::before {
    content: attr(data-cms-partial-label);
    position: absolute;
    inset-block-start: -0.75rem;
    inset-inline-start: 0;
    padding: 0 0.35rem;
    font: 600 0.6875rem/1.4 system-ui, sans-serif;
    color: #ffffff;
    background: #6d28d9;
    border-radius: 2px;
    pointer-events: none;
}
.cms-partial__notice {
    margin: 0;
    padding: 1rem;
    font: 0.875rem/1.5 system-ui, sans-serif;
    color: #374151;
    background: #f3f4f6;
    text-align: center;
}
`;

/**
 * Blocks whose content is produced by the server.
 *
 * What a page stores for one of them is its name and its settings, nothing
 * else: `<div data-cms-partial="latest-contents" data-props='{"count":3}'></div>`.
 * The canvas shows a preview fetched from the server, but that preview is
 * written straight into the DOM and never becomes part of the component, so it
 * cannot end up in the exported HTML — which is the whole point: the news list
 * of a page has to be today's news, not the news of the day it was published.
 *
 * Options: `partials` (the definitions, straight from the server registry),
 * `endpoint` (where a preview is rendered), `locale` (the language of the page
 * being edited) and `labels` (already translated).
 */
export default (editor, options = {}) => {
    const { partials = [], endpoint = null, locale = "", labels = {}, category = "Dynamic" } = options;

    if (partials.length === 0) {
        return;
    }

    const definitions = new Map(partials.map((partial) => [partial.name, partial]));

    editor.Components.addType(TYPE, {
        isComponent: (element) =>
            element?.getAttribute?.(NAME_ATTRIBUTE) ? { type: TYPE, name: element.getAttribute(NAME_ATTRIBUTE) } : undefined,

        model: {
            defaults: {
                droppable: false,
                editable: false,
                // The preview is markup from the site, not something to style or
                // pick apart in the layer tree.
                selectable: true,
                hoverable: true,
                components: [],
                attributes: {},
            },

            init() {
                const name = this.getAttributes()[NAME_ATTRIBUTE] ?? this.get("name");

                this.set("name", name, { silent: true });
                this.readStoredProps();
                this.set("traits", traitsFor(definitions.get(name)));

                // Every setting is a model property rather than an attribute of
                // its own: the sanitiser only lets `data-props` through, and one
                // attribute per setting would need a wider allow list on the
                // server for no benefit.
                this.on("change", this.writeProps);
            },

            /**
             * Loads the settings of a block that comes from a stored page.
             */
            readStoredProps() {
                const definition = definitions.get(this.get("name"));

                if (!definition) {
                    return;
                }

                let stored = {};

                try {
                    stored = JSON.parse(this.getAttributes()[PROPS_ATTRIBUTE] || "{}") ?? {};
                } catch {
                    stored = {};
                }

                definition.props.forEach((prop) => {
                    const value = stored[prop.name] ?? prop.default ?? "";

                    this.set(prop.name, value, { silent: true });
                });
            },

            writeProps() {
                const definition = definitions.get(this.get("name"));

                if (!definition) {
                    return;
                }

                const props = {};

                definition.props.forEach((prop) => {
                    const value = this.get(prop.name);

                    if (value !== undefined && value !== null && value !== "") {
                        props[prop.name] = value;
                    }
                });

                this.addAttributes({ [NAME_ATTRIBUTE]: definition.name, [PROPS_ATTRIBUTE]: JSON.stringify(props) });
                this.view?.refreshPreview();
            },
        },

        view: {
            onRender() {
                this.refreshPreview();
            },

            async refreshPreview() {
                const model = this.model;
                const definition = definitions.get(model.get("name"));

                if (!definition) {
                    this.showNotice(labels.unknown ?? "This block is not available on this site.");

                    return;
                }

                if (!endpoint) {
                    this.showNotice(definition.label);

                    return;
                }

                const props = {};
                definition.props.forEach((prop) => {
                    props[prop.name] = model.get(prop.name);
                });

                // A block dropped on the page has no settings yet, so the first
                // answer is usually "tell me which menu": it is shown as a note
                // rather than as an error.
                try {
                    const response = await fetch(endpoint, {
                        method: "POST",
                        credentials: "same-origin",
                        headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest" },
                        body: JSON.stringify({ name: definition.name, props, locale }),
                    });

                    const payload = await response.json();

                    if (!response.ok) {
                        this.showNotice(payload.error || labels.failed || "This block cannot be previewed.");

                        return;
                    }

                    // A block waiting for a setting is answered with the
                    // sentence saying which one, not with an error.
                    if (payload.notice) {
                        this.showNotice(payload.notice);

                        return;
                    }

                    this.showPreview(definition, payload.html ?? "");
                } catch {
                    this.showNotice(labels.failed ?? "This block cannot be previewed.");
                }
            },

            showPreview(definition, html) {
                this.el.classList.add("cms-partial");
                this.el.setAttribute("data-cms-partial-label", definition.label);

                // Straight into the DOM: assigning components here would put the
                // rendered markup into the page that gets saved.
                this.el.innerHTML =
                    html.trim() === "" ? this.noticeMarkup(labels.empty ?? "Nothing to show yet.") : html;
            },

            showNotice(message) {
                this.el.classList.add("cms-partial");
                this.el.innerHTML = this.noticeMarkup(message);
            },

            noticeMarkup(message) {
                const notice = document.createElement("p");
                notice.className = "cms-partial__notice";
                notice.textContent = message;

                return notice.outerHTML;
            },
        },
    });

    editor.on("load", () => {
        const canvasDocument = editor.Canvas.getDocument();

        if (!canvasDocument || canvasDocument.getElementById("cms-partial-chrome")) {
            return;
        }

        const style = canvasDocument.createElement("style");
        style.id = "cms-partial-chrome";
        style.textContent = CANVAS_CSS;
        canvasDocument.head.appendChild(style);
    });

    partials.forEach((partial) => {
        editor.Blocks.add(`cms-partial:${partial.name}`, {
            label: partial.label,
            media: ICON,
            category,
            select: true,
            content: {
                type: TYPE,
                name: partial.name,
                attributes: { [NAME_ATTRIBUTE]: partial.name },
            },
        });
    });
};

/**
 * Turns the settings a partial declares on the server into editor traits.
 *
 * `changeProp` throughout: a trait writing an attribute would put one attribute
 * per setting in the page, where the server expects them all inside
 * `data-props`.
 */
function traitsFor(definition) {
    if (!definition) {
        return [];
    }

    return definition.props.map((prop) => {
        const base = { name: prop.name, label: prop.label, changeProp: true };

        if (prop.help) {
            base.title = prop.help;
        }

        switch (prop.type) {
            case "integer":
                return { ...base, type: "number", min: prop.min, max: prop.max };
            case "boolean":
                return { ...base, type: "checkbox", valueTrue: "1", valueFalse: "0" };
            case "choice":
                return {
                    ...base,
                    type: "select",
                    options: Object.entries(prop.choices ?? {}).map(([value, name]) => ({ id: value, name })),
                };
            case "reference":
                return { ...base, type: "select-api", endpoint: prop.source, valueKey: "id", labelKey: "name" };
            default:
                return { ...base, type: "text" };
        }
    });
}
