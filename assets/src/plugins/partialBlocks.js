import { TOKEN_HEADER, backOfficeToken } from "../backOfficeToken.js";

const TYPE = "cms-partial";
const NAME_ATTRIBUTE = "data-cms-partial";
const PROPS_ATTRIBUTE = "data-props";
const SEARCH_TRAIT = "cms-reference-search";

/** Below this, a search would match half the source. */
const SEARCH_MIN_LENGTH = 2;
/** Typing pause before a search is sent: one request per word, not per key. */
const SEARCH_DELAY = 250;

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

    registerReferenceSearch(editor, labels);

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
                this.set("traits", traitsFor(definitions.get(name), labels));

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
                        headers: {
                            "Content-Type": "application/json",
                            "X-Requested-With": "XMLHttpRequest",
                            [TOKEN_HEADER]: backOfficeToken(),
                        },
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

    // A block switched off under CMS > Settings was described above with
    // the others, so a page that already holds it keeps its preview and its
    // settings; it is only left out of the panel.
    partials.filter((partial) => partial.enabled !== false).forEach((partial) => {
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
function traitsFor(definition, labels = {}) {
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
                return prop.searchable
                    ? { ...base, type: SEARCH_TRAIT, source: prop.source, labels }
                    : { ...base, type: "select-api", endpoint: prop.source, valueKey: "id", labelKey: "name" };
            default:
                return { ...base, type: "text" };
        }
    });
}

/**
 * A reference whose source is too long for a select: the author types, the
 * source answers what matches (`?q=`), the author picks one. The name of the
 * record already chosen is asked for on its own (`?id=`), so a block opened
 * again says what it shows. Both answers are a list of `{id, name}`, the shape
 * of every reference source.
 *
 * The value is written straight on the component, as `changeProp` traits do:
 * the search box itself is never the value, only the way to find it.
 */
function registerReferenceSearch(editor, labels) {
    editor.TraitManager.addType(SEARCH_TRAIT, {
        eventCapture: [],

        createInput({ trait, component }) {
            const name = trait.get("name");
            const source = trait.get("source");
            const texts = trait.get("labels") ?? labels;

            const wrap = document.createElement("div");
            wrap.className = "cms-reference-search";

            const current = document.createElement("p");
            current.className = "cms-reference-search__current";
            current.setAttribute("aria-live", "polite");

            const input = document.createElement("input");
            input.type = "search";
            input.className = "cms-reference-search__input";
            input.autocomplete = "off";
            input.placeholder = texts.searchPlaceholder ?? "Type to search";
            input.setAttribute("aria-label", trait.get("label") ?? name);

            const status = document.createElement("p");
            status.className = "cms-reference-search__status";
            status.setAttribute("aria-live", "polite");

            const results = document.createElement("ul");
            results.className = "cms-reference-search__results";

            const showCurrent = () => {
                const value = component.get(name);

                if (value === undefined || value === null || value === "") {
                    current.textContent = texts.searchNone ?? "Nothing chosen yet.";

                    return;
                }

                fetchReferences(source, { id: value })
                    .then((items) => {
                        current.textContent = items[0]?.name ?? texts.searchMissing ?? "The record chosen is no longer available.";
                    })
                    .catch(() => {
                        current.textContent = String(value);
                    });
            };

            const pick = (item) => {
                component.set(name, String(item.id));
                current.textContent = item.name;
                input.value = "";
                results.replaceChildren();
                status.textContent = "";
            };

            let timer = null;
            let pending = null;

            const search = (query) => {
                pending?.abort();
                pending = new AbortController();
                status.textContent = texts.searching ?? "Searching…";

                fetchReferences(source, { q: query }, pending.signal)
                    .then((items) => {
                        results.replaceChildren(
                            ...items.map((item) => {
                                const entry = document.createElement("li");
                                const button = document.createElement("button");
                                button.type = "button";
                                button.className = "cms-reference-search__result";
                                button.textContent = item.name;
                                button.addEventListener("click", () => pick(item));
                                entry.append(button);

                                return entry;
                            }),
                        );
                        status.textContent = items.length === 0 ? (texts.searchEmpty ?? "No result.") : "";
                    })
                    .catch((error) => {
                        if (error.name !== "AbortError") {
                            results.replaceChildren();
                            status.textContent = texts.searchFailed ?? "The search failed. Try again.";
                        }
                    });
            };

            input.addEventListener("input", () => {
                clearTimeout(timer);
                const query = input.value.trim();

                if (query.length < SEARCH_MIN_LENGTH) {
                    pending?.abort();
                    results.replaceChildren();
                    status.textContent = "";

                    return;
                }

                timer = setTimeout(() => search(query), SEARCH_DELAY);
            });

            // Enter in the box would otherwise reach the editor's keymaps.
            input.addEventListener("keydown", (event) => {
                if (event.key === "Enter") {
                    event.preventDefault();
                    results.querySelector("button")?.click();
                }
            });

            showCurrent();
            wrap.append(current, input, status, results);

            return wrap;
        },

        onEvent() {},

        // The value lives on the component; nothing in the box mirrors it.
        onUpdate() {},
    });
}

function fetchReferences(source, params, signal) {
    const url = new URL(source, window.location.origin);

    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));

    return fetch(url, { credentials: "same-origin", headers: { Accept: "application/json" }, signal })
        .then((response) => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            return response.json();
        })
        .then((items) => {
            if (!Array.isArray(items)) {
                throw new Error("A reference source answers a list.");
            }

            return items;
        });
}
