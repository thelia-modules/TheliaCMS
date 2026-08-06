import { existsSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import * as esbuild from "esbuild";

const here = dirname(fileURLToPath(import.meta.url));

/**
 * The editor sources come from openstudio/page-builder-bundle, a Composer
 * package: its location depends on where the project put the module (local
 * modules, vendor modules...), so it is looked up rather than hard-coded.
 */
function locatePageBuilderBundle() {
    let directory = here;

    for (let depth = 0; depth < 8; depth++) {
        const candidate = join(directory, "vendor/openstudio/page-builder-bundle/assets");

        if (existsSync(candidate)) {
            return candidate;
        }

        const parent = dirname(directory);

        if (parent === directory) {
            break;
        }

        directory = parent;
    }

    throw new Error(
        "openstudio/page-builder-bundle was not found. Run `composer install` on the project holding this module before building the editor.",
    );
}

const bundleAssets = locatePageBuilderBundle();
const outputDirectory = resolve(here, "../templates/backOffice/default-twig/assets/page-builder");

const options = {
    entryPoints: [resolve(here, "src/editor.js")],
    outfile: join(outputDirectory, "editor.js"),
    bundle: true,
    minify: true,
    format: "iife",
    target: ["es2020"],
    sourcemap: false,
    legalComments: "none",
    loader: {
        ".png": "dataurl",
        ".svg": "dataurl",
        ".ttf": "dataurl",
        ".woff": "dataurl",
        ".woff2": "dataurl",
    },
    alias: {
        "@page-builder": bundleAssets,
        // The bundle imports the locale without its extension, which the
        // GrapesJS export map only accepts through an importmap. Reported
        // upstream; harmless to map here.
        "grapesjs/locale/fr": resolve(here, "node_modules/grapesjs/locale/fr.js"),
    },
    // The bundle sources live outside this directory, so their bare imports
    // (grapesjs and its plugins) would otherwise be resolved against the
    // Composer vendor tree, which has no node_modules.
    nodePaths: [resolve(here, "node_modules")],
    logLevel: "info",
};

if (process.argv.includes("--watch")) {
    const context = await esbuild.context(options);
    await context.watch();
} else {
    await esbuild.build(options);
}
