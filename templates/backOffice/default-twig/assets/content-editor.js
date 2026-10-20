/*
 * Content screen of the Thelia CMS back office.
 *
 * Two jobs, both progressive enhancement: the forms that ask the generator
 * to write are posted in the background and the progress of the call is
 * shown as it runs, instead of a blank half minute; and the notices over the
 * stage can be closed and dismiss themselves.
 *
 * A plain script rather than a Stimulus controller: a module cannot register
 * a controller in the application of the theme.
 */
(function () {
    "use strict";

    var root = document.querySelector("[data-cms-content]");

    if (!root) {
        return;
    }

    var labels = JSON.parse(root.getAttribute("data-cms-content-labels") || "{}");
    var progress = root.querySelector("[data-cms-progress]");
    var steps = root.querySelector("[data-cms-progress-steps]");
    var running = false;

    /* ---- Notices --------------------------------------------------- */

    function dismiss(toast) {
        clearTimeout(toast.cmsTimer);
        toast.classList.remove("cms-toast--visible");
        toast.addEventListener("transitionend", function () { toast.remove(); }, { once: true });
        setTimeout(function () { toast.remove(); }, 400);
    }

    function arm(toast) {
        toast.classList.add("cms-toast--visible");

        if (toast.getAttribute("data-cms-toast-permanent-value") === "true") {
            return;
        }

        var resume = function () {
            clearTimeout(toast.cmsTimer);
            toast.cmsTimer = setTimeout(function () { dismiss(toast); }, 6000);
        };
        var pause = function () { clearTimeout(toast.cmsTimer); };

        toast.addEventListener("mouseenter", pause);
        toast.addEventListener("mouseleave", resume);
        toast.addEventListener("focusin", pause);
        toast.addEventListener("focusout", resume);
        resume();
    }

    document.querySelectorAll(".cms-toast").forEach(arm);

    document.addEventListener("click", function (event) {
        var close = event.target.closest(".cms-toast__close");

        if (close) {
            dismiss(close.closest(".cms-toast"));
        }
    });

    function notify(type, message) {
        var container = document.querySelector(".cms-toasts");

        if (!container) {
            container = document.createElement("div");
            container.className = "cms-toasts";
            root.appendChild(container);
        }

        var toast = document.createElement("div");
        toast.className = "cms-toast cms-toast--" + type;
        toast.setAttribute("role", type === "danger" ? "alert" : "status");
        toast.setAttribute("data-cms-toast-permanent-value", type === "danger" ? "true" : "false");

        var icon = document.createElement("i");
        icon.className = "bi cms-toast__icon " + (type === "danger" ? "bi-exclamation-octagon-fill" : "bi-info-circle-fill");
        icon.setAttribute("aria-hidden", "true");

        var text = document.createElement("p");
        text.className = "cms-toast__message";
        text.textContent = message;

        var close = document.createElement("button");
        close.type = "button";
        close.className = "cms-toast__close";
        close.setAttribute("aria-label", labels.close || "Close");
        close.textContent = "×";

        toast.append(icon, text, close);
        container.appendChild(toast);
        requestAnimationFrame(function () { arm(toast); });
    }

    /* ---- Generation ------------------------------------------------ */

    function addStep(message, warning) {
        var item = document.createElement("li");
        item.textContent = message;

        if (warning) {
            item.classList.add("is-warning");
        }

        steps.appendChild(item);
    }

    function setBusy(busy) {
        running = busy;
        root.querySelectorAll("form[data-cms-generate] button[type=submit]").forEach(function (button) {
            button.disabled = busy || button.hasAttribute("data-cms-disabled");
        });
        progress.hidden = !busy;

        if (busy) {
            steps.replaceChildren();
        }
    }

    root.querySelectorAll("form[data-cms-generate] button[type=submit][disabled]").forEach(function (button) {
        button.setAttribute("data-cms-disabled", "");
    });

    /**
     * Reads a stream of server-sent events and calls back for each one.
     * Written by hand: EventSource only sends GET requests, and the call is
     * a POST carrying the brief and the token.
     */
    function readEvents(response, onEvent) {
        var reader = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer = "";

        function handle(chunk) {
            var lines = chunk.split("\n");
            var name = "message";
            var data = "";

            lines.forEach(function (line) {
                if (line.indexOf("event:") === 0) {
                    name = line.slice(6).trim();
                } else if (line.indexOf("data:") === 0) {
                    data += line.slice(5).trim();
                }
            });

            if (data !== "") {
                onEvent(name, JSON.parse(data));
            }
        }

        return reader.read().then(function step(result) {
            if (result.done) {
                if (buffer.trim() !== "") {
                    handle(buffer);
                }

                return;
            }

            buffer += decoder.decode(result.value, { stream: true });
            var parts = buffer.split("\n\n");
            buffer = parts.pop();
            parts.forEach(handle);

            return reader.read().then(step);
        });
    }

    function generate(form) {
        setBusy(true);
        var finished = false;

        fetch(form.action, {
            method: "POST",
            body: new FormData(form),
            credentials: "same-origin",
            headers: {
                "Accept": "text/event-stream",
                "X-Requested-With": "XMLHttpRequest"
            }
        }).then(function (response) {
            if (!response.ok || (response.headers.get("Content-Type") || "").indexOf("text/event-stream") === -1) {
                throw new Error(labels.failed.replace("%status%", response.status));
            }

            return readEvents(response, function (name, event) {
                if (name === "start" || name === "turn") {
                    addStep(event.message, false);
                } else if (name === "correction") {
                    addStep(event.message, true);
                } else if (name === "done") {
                    finished = true;
                    window.location.assign(event.redirect);
                } else if (name === "error") {
                    finished = true;

                    if (event.redirect) {
                        window.location.assign(event.redirect);
                    } else {
                        setBusy(false);
                        notify("danger", event.message);
                    }
                }
            });
        }).then(function () {
            if (!finished) {
                setBusy(false);
                notify("danger", labels.network);
            }
        }).catch(function (error) {
            setBusy(false);
            notify("danger", error.message || labels.network);
        });
    }

    root.querySelectorAll("form[data-cms-generate]").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (!window.fetch || !window.ReadableStream || running) {
                return;
            }

            if (!form.reportValidity()) {
                event.preventDefault();

                return;
            }

            event.preventDefault();
            generate(form);
        });
    });

    window.addEventListener("beforeunload", function (event) {
        if (running) {
            event.preventDefault();
            event.returnValue = labels.leaving;
        }
    });
})();
