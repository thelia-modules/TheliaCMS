import { TOKEN_HEADER, backOfficeToken } from "../backOfficeToken.js";

/**
 * Wires the image library of the editor to the media endpoints of the module.
 *
 * Same contract as the `pb:asset-manager-upload` plugin of the page builder
 * bundle, which it replaces: that one sends its upload and its deletion without
 * the token of the back office, and the server refuses both.
 *
 * Options: `endpoints.uploadImage` (POST, multipart, field `files`),
 * `endpoints.listImages` (GET `?context=`), `endpoints.media` (DELETE
 * `{media}/{id}`), `context`, forwarded to the upload and the list, and
 * `closeLabel`, the name of the close button of a notice.
 *
 * The server refuses to delete an image a page or a block still shows: the
 * image goes back into the library and the reason is shown in a notice, built
 * like the flash messages of the builder screens.
 */
export default (editor, options = {}) => {
    const uploadEndpoint = options.endpoints?.uploadImage;
    const listEndpoint = options.endpoints?.listImages;
    const mediaEndpoint = options.endpoints?.media;
    const closeLabel = options.closeLabel || "Close";
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

        if (!assetId || !mediaEndpoint) {
            return;
        }

        try {
            const response = await fetch(`${mediaEndpoint}/${assetId}`, {
                method: "DELETE",
                credentials: "same-origin",
                headers: { [TOKEN_HEADER]: backOfficeToken(), "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
            });

            if (!response.ok) {
                // GrapesJS has already taken the image out of the library.
                assetManager.add(asset);
                notify(await refusalOf(response), "warning", closeLabel);

                return;
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
            assetManager.add(asset);
            console.error("The image could not be deleted.", error);
        }
    };

    editor.on("load", loadExistingAssets);
    editor.on("asset:remove", deleteAsset);
    editor.on("asset:upload:error", (error) => {
        console.error("The image could not be uploaded.", error);
        notify("Upload failed. Please try again.", "danger", closeLabel);
    });
};

const ICONS = { danger: "bi-exclamation-octagon-fill", warning: "bi-exclamation-triangle-fill" };

/**
 * Same markup as `_toasts.html.twig`, so the `cms-toast` controller handles it:
 * a warning is announced politely and dismisses itself, an error stays.
 */
function notify(message, type, closeLabel) {
    let container = document.querySelector(".cms-toasts");

    if (!container) {
        container = document.createElement("div");
        container.className = "cms-toasts";
        document.body.append(container);
    }

    const toast = document.createElement("div");
    toast.className = `cms-toast cms-toast--${type}`;
    toast.dataset.controller = "cms-toast";
    toast.dataset.cmsToastPermanentValue = type === "danger" ? "true" : "false";
    toast.dataset.testid = `cms-builder-flash-${type}`;
    toast.setAttribute("role", type === "danger" ? "alert" : "status");

    const icon = document.createElement("i");
    icon.className = `bi ${ICONS[type] ?? "bi-info-circle-fill"} cms-toast__icon`;
    icon.setAttribute("aria-hidden", "true");

    const text = document.createElement("p");
    text.className = "cms-toast__message";
    text.textContent = message;

    const close = document.createElement("button");
    close.type = "button";
    close.className = "cms-toast__close";
    close.dataset.action = "cms-toast#close";
    close.setAttribute("aria-label", closeLabel);
    close.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';

    toast.append(icon, text, close);
    container.append(toast);
}

/**
 * The reason the server gives in `error`, or the status when it gives none.
 */
async function refusalOf(response) {
    try {
        const payload = await response.json();

        if (typeof payload?.error === "string" && payload.error !== "") {
            return payload.error;
        }
    } catch {
        // Not JSON: an error page of the framework.
    }

    return `The image could not be deleted (HTTP ${response.status}).`;
}

function normalizeUrl(url) {
    try {
        return new URL(url).href;
    } catch {
        return url;
    }
}
