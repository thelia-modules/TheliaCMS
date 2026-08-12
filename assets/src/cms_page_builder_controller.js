import PageBuilderController from "@page-builder/controllers/page_builder_controller.js";
import partialBlocks from "./plugins/partialBlocks.js";
import catalogBlocks from "./plugins/catalogBlocks.js";
import headingLevels from "./plugins/headingLevels.js";
import richTextTrait from "./plugins/richTextTrait.js";

/**
 * Thelia flavour of the page builder controller.
 *
 * Two things the base controller cannot decide on its own:
 * free HTML editing is an authorisation, not a default; and the editor has to
 * speak the language the administrator picked in the back office rather than
 * the one their browser happens to advertise.
 */
export default class extends PageBuilderController {
    static values = {
        ...PageBuilderController.values,
        allowCustomCode: { type: Boolean, default: false },
        locale: { type: String, default: "" },
        autosaveInterval: { type: Number, default: 30000 },
        // Wording of the two empty states, translated server-side: the editor
        // shows a blank canvas and a blank settings panel and explains neither.
        emptyTitle: { type: String, default: "" },
        emptyHint: { type: String, default: "" },
        panelHint: { type: String, default: "" },
        // Wording GrapesJS ships in English only, translated server-side like
        // the rest of the screen.
        editorLabels: { type: Object, default: {} },
        // Server-rendered blocks: the registry, the language of the page being
        // edited, and the wording shown while a block has nothing to display.
        partials: { type: Array, default: [] },
        // The block catalogue, described by the server so its sample text is in
        // the language of the page rather than of the back office.
        catalog: { type: Array, default: [] },
        contentLocale: { type: String, default: "" },
        partialCategory: { type: String, default: "Dynamic" },
        partialLabels: { type: Object, default: {} },
    };

    connect() {
        super.connect();

        // The editor keeps its content in memory: without this the hidden
        // fields still hold the state the screen was opened with, and saving
        // silently discards everything done since.
        this.form = this.element.closest("form");

        if (this.form) {
            this.storeBeforeSubmit = this.storeBeforeSubmit.bind(this);
            this.form.addEventListener("submit", this.storeBeforeSubmit);
            this.startAutosave();
        }

        this.warnBeforeLeaving = this.warnBeforeLeaving.bind(this);
        window.addEventListener("beforeunload", this.warnBeforeLeaving);
    }

    disconnect() {
        this.form?.removeEventListener("submit", this.storeBeforeSubmit);
        window.removeEventListener("beforeunload", this.warnBeforeLeaving);
        clearInterval(this.autosaveTimer);
        this.layerDeleteObserver?.disconnect();

        super.disconnect();
    }

    /**
     * Saves the draft in the background so a closed tab, a lost connection or
     * a session that expires do not take an afternoon of work with them.
     */
    startAutosave() {
        if (this.autosaveIntervalValue <= 0) {
            return;
        }

        this.autosaveTimer = setInterval(() => this.autosave(), this.autosaveIntervalValue);
    }

    async autosave() {
        if (this.submitting || !this.editor || !this.editor.getDirtyCount()) {
            return;
        }

        this.writeFieldsFromEditor();

        const payload = new FormData(this.form);
        payload.set("save", "autosave");

        try {
            // The whole form is posted, token included, so the request is
            // checked exactly like a normal submission.
            const response = await fetch(this.form.action || window.location.href, {
                method: "POST",
                body: payload,
                credentials: "same-origin",
                headers: { "X-Requested-With": "XMLHttpRequest" },
            });

            if (response.ok) {
                this.editor.clearDirtyCount();
                this.dispatch("autosaved");
            }
        } catch {
            // Offline or server down: the next tick tries again, and the
            // unsaved-changes guard still stands.
        }
    }

    warnBeforeLeaving(event) {
        if (this.submitting || !this.editor?.getDirtyCount()) {
            return;
        }

        event.preventDefault();
        event.returnValue = "";
    }

    /**
     * Rebuilds the plugin list from scratch — the parent method assigns it
     * rather than appending to it, so anything left out here is off.
     *
     * `grapesjs:custom-code` is off by default: a page editor is not a trusted
     * profile, and free HTML belongs behind the `admin.cms.custom-code`
     * resource. `pb:trait-select-api` is on, it is what feeds the blocks that
     * pick a page, a folder or a form.
     */
    configurePlugins() {
        this.pluginManager.registerPlugin("cms:partials", partialBlocks);
        this.pluginManager.registerPlugin("cms:catalog", catalogBlocks);
        this.pluginManager.registerPlugin("cms:heading-levels", headingLevels);
        this.pluginManager.registerPlugin("cms:rich-text-trait", richTextTrait);

        const labels = this.editorLabelsValue ?? {};

        const plugins = [
            "pb:init-categories",
            "pb:title",
            "pb:section",
            "grapesjs:blocks-basic",
            "grapesjs:preset-webpage",
            // After the preset: it rewrites blocks the preset has just added.
            "cms:heading-levels",
            "pb:list",
            "pb:divider",
            { name: "pb:icon", options: { icons: this.iconsValue } },
            "pb:accordion",
            "grapesjs:countdown",
            { name: "pb:table", options: { container: this.editorTarget } },
            "pb:trait-select-api",
            "pb:trait-select-icon",
            // The writing area of the settings panel, on every editorial block.
            { name: "cms:rich-text-trait", options: { labels: labels.richTextTrait ?? {} } },
            "pb:reorganize-blocks",
        ];

        if (this.allowCustomCodeValue) {
            plugins.push("grapesjs:custom-code");
        }

        if (this.hasFormFields()) {
            plugins.push({ name: "pb:form-storage", options: { fields: this.fieldsValue } });
            plugins.push("pb:button-save");
        }

        if (this.endpointsValue?.uploadImage) {
            plugins.push({ name: "pb:asset-manager-upload", options: { endpoints: this.endpointsValue, context: this.contextValue } });
        }

        if (this.catalogValue.length > 0) {
            plugins.push({ name: "cms:catalog", options: { blocks: this.catalogValue } });
        }

        if (this.partialsValue.length > 0) {
            plugins.push({
                name: "cms:partials",
                options: {
                    partials: this.partialsValue,
                    endpoint: this.endpointsValue?.["render-template"] ?? null,
                    // The language of the page, not the one the back office is
                    // displayed in: a preview must read like the page will.
                    locale: this.contentLocaleValue,
                    category: this.partialCategoryValue,
                    labels: this.partialLabelsValue,
                },
            });
        }

        this.pluginManager.initActivePlugins(plugins);
    }

    /**
     * The palette of the site opens first, as before, but it stops being a
     * wall: a button switches to the full wheel for the one-off colour the
     * palette does not have. The two texts of that button are wording, so
     * they come from the server like the rest.
     */
    buildColorPicker() {
        const picker = super.buildColorPicker();

        if (!picker) {
            return picker;
        }

        const labels = this.editorLabelsValue ?? {};
        const texts = labels.colorPicker ?? {};

        return {
            ...picker,
            togglePaletteOnly: true,
            ...(texts.more ? { togglePaletteMoreText: texts.more } : {}),
            ...(texts.less ? { togglePaletteLessText: texts.less } : {}),
        };
    }

    initEditor(options = {}) {
        // The bundle leaves the storage on its defaults, and the default is to
        // store after every single change — which writes the hidden fields and
        // resets the change counter to zero on the spot. Both the background
        // save and the unsaved-changes guard ask that counter whether anything
        // is left to save, so both were answered "no" forever. With autosave
        // off, the counter counts, and the fields are written by whoever
        // saves: the background save, and the submit handler.
        if (this.hasFormFields()) {
            options = { storageManager: { type: "form", autosave: false }, ...options };
        }

        super.initEditor(options);

        // Handle on the GrapesJS instance for anything driving the editor from
        // outside the controller — an integrator's script, an end-to-end test.
        this.element.gjsEditor = this.editor;

        // The editor language cannot be passed through the init options: the
        // bundle builds the i18n block itself, so it is set afterwards.
        if (this.localeValue) {
            this.editor.I18n.setLocale(this.localeValue);
        }

        this.translateWhatGrapesJsLeavesInEnglish();
        this.labelTheViewTabs();
        this.addDeleteToTheLayerRows();
        this.explainTheEmptyCanvas();
        this.explainTheEmptySettingsPanel();
    }

    /**
     * Fills the two holes GrapesJS leaves in every language but English, so the
     * editor does not read half in one language and half in another.
     *
     * The wording comes from the server, like the rest of the screen, rather
     * than from a table of strings inside the bundle.
     *
     * Two holes, two mechanisms:
     *
     * - the label of the `target` trait has no key in the shipped locale
     *   files, so the raw trait name is displayed; addMessages fills it in and
     *   is a no-op the day the library or the bundle ships it;
     * - the buttons of the rich-text toolbar carry their title as a plain
     *   attribute set when the library builds them, outside its own i18n. The
     *   title is written on the action, for buttons not built yet, and on the
     *   button itself, for those already there.
     */
    translateWhatGrapesJsLeavesInEnglish() {
        const labels = this.editorLabelsValue;

        if (!labels || 0 === Object.keys(labels).length) {
            return;
        }

        const messages = {};

        if (labels.linkTarget) {
            messages.traitManager = { traits: { labels: { target: labels.linkTarget } } };
        }

        // "Component settings", said to an editor who was told everything on
        // the page is a block.
        if (labels.settingsTitle) {
            messages.traitManager = { ...(messages.traitManager ?? {}), label: labels.settingsTitle };
        }

        // The values of the style options (left, solid, no-repeat...) have no
        // key in the locale files the library ships: whatever language the
        // screen is in, they come out as raw CSS keywords.
        if (labels.styleOptions) {
            messages.styleManager = { options: labels.styleOptions };
        }

        if (Object.keys(messages).length > 0) {
            this.editor.I18n.addMessages({ [this.editor.I18n.getLocale()]: messages });
        }

        for (const [name, title] of Object.entries(labels.richText ?? {})) {
            const action = this.editor.RichTextEditor.get(name);

            if (!action) {
                continue;
            }

            action.attributes = { ...(action.attributes ?? {}), title };
            action.btn?.setAttribute("title", title);
        }
    }

    /**
     * The three tabs of the settings panel are icons alone, and the only mark
     * of the active one is a tint. A word under each icon says what the tab
     * holds without hovering for the tooltip.
     *
     * The word goes into the label of the button model, not into the rendered
     * element: the view is rebuilt from that label every time the active tab
     * changes, and anything appended to the element goes with it.
     */
    labelTheViewTabs() {
        const labels = this.editorLabelsValue ?? {};
        const tabs = labels.viewTabs;

        if (!tabs || 0 === Object.keys(tabs).length) {
            return;
        }

        this.editor.on("load", () => {
            for (const [id, text] of Object.entries(tabs)) {
                const button = this.editor.Panels.getButton("views", id);

                if (!button) {
                    continue;
                }

                // The label is an HTML string holding the icon; the wording
                // goes through a text node so it stays wording.
                const label = document.createElement("span");
                label.className = "cms-builder__tab-label";
                label.textContent = text;

                button.set("label", String(button.get("label") ?? "") + label.outerHTML);
            }
        });
    }

    /**
     * A delete control on every row of the layer tree.
     *
     * Deleting from the tree otherwise takes selecting the row and knowing
     * that the keyboard, or the toolbar over the canvas, can delete what is
     * selected — nothing in the panel says so.
     *
     * The rows carry no identifier, so each button holds the component of its
     * row, read from the data GrapesJS leaves on the row element. Rows are
     * redrawn whenever the tree changes, buttons and all: an observer sweeps
     * the panel and equips whatever row is missing one. Deleting through the
     * command keeps it undoable, like the delete of the canvas toolbar.
     */
    addDeleteToTheLayerRows() {
        const labels = this.editorLabelsValue ?? {};

        if (!labels.deleteLayer) {
            return;
        }

        this.editor.on("load", () => {
            const panel = this.element.querySelector(".gjs-pn-views-container");

            if (!panel) {
                return;
            }

            const sweep = () => {
                for (const row of panel.querySelectorAll(".gjs-layer")) {
                    if (row.querySelector(":scope > .gjs-layer-item > .cms-builder__layer-delete")) {
                        continue;
                    }

                    const component = row.__cashData?.model;

                    // The root of the tree is the page itself, and a block may
                    // be marked as not removable by whoever registered it.
                    if (!component || component === this.editor.getWrapper() || false === component.get("removable")) {
                        continue;
                    }

                    const item = row.querySelector(":scope > .gjs-layer-item");

                    if (!item) {
                        continue;
                    }

                    const button = document.createElement("button");
                    button.type = "button";
                    button.className = "cms-builder__layer-delete";
                    button.title = labels.deleteLayer;
                    button.setAttribute("aria-label", labels.deleteLayer);
                    button.innerHTML =
                        '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path fill="currentColor" d="M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z"/></svg>';

                    button.addEventListener("click", (event) => {
                        // The row underneath selects the component on click.
                        event.stopPropagation();
                        this.editor.runCommand("core:component-delete", { component });
                    });

                    item.append(button);
                }
            };

            this.layerDeleteObserver = new MutationObserver(sweep);
            this.layerDeleteObserver.observe(panel, { childList: true, subtree: true });
            sweep();
        });
    }

    /**
     * A page with no content opens on a blank white area that says nothing
     * about blocks being dragged onto it.
     *
     * The note sits above the canvas rather than in it — inside, it would
     * become part of the page — and lets clicks and drops through.
     */
    explainTheEmptyCanvas() {
        if (!this.emptyTitleValue) {
            return;
        }

        const note = document.createElement("div");
        note.className = "cms-builder__empty";
        note.setAttribute("aria-hidden", "true");
        note.innerHTML = '<p class="cms-builder__empty-title"></p><p class="cms-builder__empty-hint"></p>';
        note.querySelector(".cms-builder__empty-title").textContent = this.emptyTitleValue;
        note.querySelector(".cms-builder__empty-hint").textContent = this.emptyHintValue;

        this.editorTarget.append(note);

        const refresh = () => {
            note.hidden = (this.editor.getWrapper()?.components().length ?? 0) > 0;
        };

        this.editor.on("load component:add component:remove", refresh);
        refresh();
    }

    /**
     * The settings panel keeps its full width whether or not there is anything
     * to settle, so an empty one reads as broken rather than as waiting for a
     * selection.
     */
    explainTheEmptySettingsPanel() {
        if (!this.panelHintValue) {
            return;
        }

        // The editor panels are not in the DOM yet when init returns.
        this.editor.on("load", () => {
            const panel = this.element.querySelector(".gjs-pn-views-container");

            if (!panel) {
                return;
            }

            const note = document.createElement("p");
            note.className = "cms-builder__panel-hint";
            note.textContent = this.panelHintValue;
            panel.append(note);

            const refresh = () => {
                // The layer tree is the one view that says something on its own
                // with nothing selected.
                const showsLayers = panel.querySelector(".gjs-layers")?.offsetParent != null;

                note.hidden = showsLayers || Boolean(this.editor.getSelected());
            };

            this.editor.on("component:selected component:deselected", refresh);
            this.element.querySelector(".gjs-pn-views")?.addEventListener("click", () => setTimeout(refresh));
            refresh();
        });
    }

    /**
     * Fills the hidden fields with what the editor holds, in the submit event
     * itself.
     *
     * `editor.store()` would do the same but returns a promise, which means
     * cancelling the submission and replaying it — and a replayed submission
     * loses the button that was pressed, which is exactly what tells the server
     * to publish rather than to save a draft. The storage writes the fields
     * synchronously, so it is called directly.
     */
    storeBeforeSubmit() {
        this.writeFieldsFromEditor();

        // The page is on its way out; the unsaved-changes guard must not fire.
        this.submitting = true;
    }

    writeFieldsFromEditor() {
        const storage = this.editor?.Storage?.get("form");

        storage?.store(this.editor.getProjectData(), {});
    }
}
