import { Application } from "@hotwired/stimulus";
import CmsPageBuilderController from "./cms_page_builder_controller.js";

import "./screen.css";

/**
 * The back-office theme runs its own Stimulus application, built by its own
 * Webpack Encore setup, which a module cannot register a controller with. A
 * second application is started here instead: each one only acts on the
 * identifiers it knows about, and this bundle is loaded on the page editing
 * screen only.
 */
const application = Application.start();

application.register("cms-page-builder", CmsPageBuilderController);

export { application };
