import { TOKEN_HEADER, backOfficeToken } from "../backOfficeToken.js";

/**
 * Wires the image library of the editor to the media endpoints of the module.
 *
 * Same contract as the `pb:asset-manager-upload` plugin of the page builder
 * bundle, which it replaces: that one sends its upload and its deletion without
 * the token of the back office, and the server refuses both.
 *
 * Options: `endpoints.uploadImage` (POST, multipart, field `files`),
 * `endpoints.listImages` (GET `?context=`, and DELETE `{listImages}/{id}`), and
 * `context`, forwarded to both.
 */
export default (editor, options = {}) => {
    const uploadEndpoint = options.endpoints?.uploadImage;
    const listEndpoint = options.endpoints?.listImages;
    const context = options.context || null;

    if (!uploadEndpoint) {
        return;
    }

    const assetManager = editor.AssetManager;
    const config = assetManager.getConfig();
    config.upload = uploadEndpoint;
    config.uploadName = "files";
    config.multiUpload = true;
    config.autoAdd = true;
    config.credentials = "same-origin";
    config.showUrlInput = false;
    config.headers = { [TOKEN_HEADER]: backOfficeToken() };

    if (context) {
        config.params = { context };
    }

    const loadExistingAssets = async () => {
        if (!context || !listEndpoint) {
            return;
        }

        try {
            const response = await fetch(`${listEndpoint}?context=${encodeURIComponent(context)}`, {
                method: "GET",
                credentials: "same-origin",
                headers: { Accept: "application/json" },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const result = await response.json();

            if (Array.isArray(result.data)) {
                assetManager.add(result.data);
            }
        } catch (error) {
            console.error("The images of the library could not be loaded.", error);
        }
    };

    const deleteAsset = async (asset) => {
        const assetId = asset.get("id");

        if (!assetId || !listEndpoint) {
            return;
        }

        try {
            const response = await fetch(`${listEndpoint}/${assetId}`, {
                method: "DELETE",
                credentials: "same-origin",
                headers: { [TOKEN_HEADER]: backOfficeToken(), "X-Requested-With": "XMLHttpRequest" },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            // The image is gone from the library: the components still
            // pointing at it would show a broken picture.
            const source = normalizeUrl(asset.get("src"));

            editor
                .getWrapper()
                .findType("image")
                .forEach((image) => {
                    if (normalizeUrl(image.get("src")) === source) {
                        image.set("src", "");
                    }
                });
        } catch (error) {
            console.error("The image could not be deleted.", error);
        }
    };

    editor.on("load", loadExistingAssets);
    editor.on("asset:remove", deleteAsset);
    editor.on("asset:upload:error", (error) => {
        console.error("The image could not be uploaded.", error);
        window.dispatchEvent(
            new CustomEvent("toast:add", {
                bubbles: true,
                detail: { message: "Upload failed. Please try again.", type: "error" },
            }),
        );
    });
};

function normalizeUrl(url) {
    try {
        return new URL(url).href;
    } catch {
        return url;
    }
}
