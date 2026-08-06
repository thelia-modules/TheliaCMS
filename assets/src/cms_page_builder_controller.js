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
        }
    }

    disconnect() {
        this.form?.removeEventListener("submit", this.storeBeforeSubmit);

        super.disconnect();
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
        const storage = this.editor?.Storage?.get("form");

        storage?.store(this.editor.getProjectData(), {});
    }
}
