import PageBuilderController from "@page-builder/controllers/page_builder_controller.js";
import partialBlocks from "./plugins/partialBlocks.js";
import catalogBlocks from "./plugins/catalogBlocks.js";

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

        const plugins = [
            "pb:init-categories",
            "pb:title",
            "pb:section",
            "grapesjs:blocks-basic",
            "grapesjs:preset-webpage",
            "pb:list",
            "pb:divider",
            { name: "pb:icon", options: { icons: this.iconsValue } },
            "pb:accordion",
            "grapesjs:countdown",
            { name: "pb:table", options: { container: this.editorTarget } },
            "pb:trait-select-api",
            "pb:trait-select-icon",
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

    initEditor(options = {}) {
        super.initEditor(options);

        // Handle on the GrapesJS instance for anything driving the editor from
        // outside the controller — an integrator's script, an end-to-end test.
        this.element.gjsEditor = this.editor;

        // The editor language cannot be passed through the init options: the
        // bundle builds the i18n block itself, so it is set afterwards.
        if (this.localeValue) {
            this.editor.I18n.setLocale(this.localeValue);
        }

        this.explainTheEmptyCanvas();
        this.explainTheEmptySettingsPanel();
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
