import PageBuilderController from "@page-builder/controllers/page_builder_controller.js";

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
