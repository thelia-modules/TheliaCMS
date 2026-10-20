/*
 * Content screen of the Thelia CMS back office.
 *
 * Two jobs, both progressive enhancement: the forms that ask the generator
 * to write are posted in the background, the progress of the call is shown
 * as it runs instead of a blank half minute, and the screen is drawn again
 * in place when the call ends, so the editor keeps their scroll and their
 * open panels; and the notices over the stage can be closed and dismiss
 * themselves.
 *
 * A plain script rather than a Stimulus controller: a module cannot register
 * a controller in the application of the theme.
 */
(function () {
    "use strict";

    var first = document.querySelector("[data-cms-content]");

    if (!first) {
        return;
    }

    var labels = JSON.parse(first.getAttribute("data-cms-content-labels") || "{}");
    var running = false;
    // Set when a section is added: the preview drawn next is scrolled to the
    // last section, the one just added, rather than left at the top.
    var revealLast = false;

    // The colour of the back office, for what the screen writes into the
    // preview: the theme of the site has its own.
    function accent() {
        return getComputedStyle(document.documentElement).getPropertyValue("--bs-primary").trim() || "#0d6efd";
    }

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

    document.addEventListener("click", function (event) {
        var close = event.target.closest(".cms-toast__close");

        if (close) {
            dismiss(close.closest(".cms-toast"));
        }
    });

    function notify(type, message) {
        var root = document.querySelector("[data-cms-content]");
        var container = root.querySelector(".cms-toasts");

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

    /**
     * Puts the screen the server answered in place of the one on show, with
     * what the reader had: the panels they opened, the scroll of the side
     * panel. The outcome of the call takes the focus, so it is read out.
     */
    function swap(html) {
        var next = new DOMParser().parseFromString(html, "text/html").querySelector("[data-cms-content]");
        var current = document.querySelector("[data-cms-content]");

        if (!next || !current) {
            throw new Error("no screen in the answer");
        }

        var opened = [];
        current.querySelectorAll("details[open]").forEach(function (panel) {
            opened.push(panelKey(panel));
        });
        var side = current.querySelector(".cms-content__panel");
        var scrolled = side ? side.scrollTop : 0;

        current.replaceWith(next);
        running = false;
        enhance(next);

        next.querySelectorAll("details").forEach(function (panel) {
            if (opened.indexOf(panelKey(panel)) !== -1) {
                panel.open = true;
            }
        });
        side = next.querySelector(".cms-content__panel");

        if (side) {
            side.scrollTop = scrolled;
        }

        var outcome = next.querySelector(".cms-toast");

        if (outcome) {
            outcome.setAttribute("tabindex", "-1");
            outcome.focus();
        }
    }

    // A panel is told apart by its section and its rank in it, which a
    // section kept by the call keeps too.
    function panelKey(panel) {
        var item = panel.closest("[data-section]");

        return item
            ? item.getAttribute("data-section") + ":" + Array.prototype.indexOf.call(item.querySelectorAll("details"), panel)
            : "";
    }

    /**
     * Draws the screen again from what the server answers for `url`: the
     * outcome of the call, the new sections, the history. The address bar
     * keeps the address of the screen, so a reload by hand does not show the
     * outcome a second time. When the answer cannot be read, the browser
     * goes there instead, which is what the screen does without its script.
     */
    function refresh(url) {
        fetch(url, {
            credentials: "same-origin",
            headers: { "X-Requested-With": "XMLHttpRequest" }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error(String(response.status));
            }

            return response.text();
        }).then(swap).catch(function () {
            window.location.assign(url);
        });
    }

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

    /**
     * Binds the behaviour of the screen to one copy of its markup: the one
     * the page was loaded with, then each one `refresh()` puts in its place.
     */
    function enhance(root) {
        var progress = root.querySelector("[data-cms-progress]");
        var steps = root.querySelector("[data-cms-progress-steps]");

        root.querySelectorAll(".cms-toast").forEach(arm);

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
                    } else if (name === "delta") {
                        // The same step, updated: one line that counts up rather
                        // than a list that scrolls.
                        var last = steps.lastElementChild;

                        if (last && last.hasAttribute("data-cms-delta")) {
                            last.textContent = event.message;
                        } else {
                            addStep(event.message, false);
                            steps.lastElementChild.setAttribute("data-cms-delta", "");
                        }
                    } else if (name === "correction") {
                        addStep(event.message, true);
                    } else if (name === "done") {
                        finished = true;
                        refresh(event.redirect);
                    } else if (name === "error") {
                        finished = true;

                        if (event.redirect) {
                            refresh(event.redirect);
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

        /* ---- The list and the preview ---------------------------------- */

        // Each section of the list is numbered, and so is its rendering in
        // the preview: hovering one shows the other, clicking the label
        // scrolls the preview to it. The preview is a page of the same site
        // in a frame; a frame from elsewhere stays silent.
        var frame = root.querySelector("[data-cms-frame]");
        var items = root.querySelectorAll("[data-cms-section-item]");

        function previewDocument() {
            try {
                var inner = frame && frame.contentDocument;

                return inner && inner.body && inner.readyState !== "loading" ? inner : null;
            } catch (error) {
                return null;
            }
        }

        function previewSection(id) {
            var inner = previewDocument();

            return inner ? inner.querySelector('[data-cms-section="' + id.replace(/"/g, '\\"') + '"]') : null;
        }

        function markPreview() {
            var inner = previewDocument();

            if (!inner || inner.getElementById("cms-content-marks")) {
                return;
            }

            var style = inner.createElement("style");
            style.id = "cms-content-marks";
            style.textContent = "[data-cms-section]{position:relative;scroll-margin-top:1rem}" +
                "[data-cms-section][data-cms-rank]::before{content:attr(data-cms-rank);position:absolute;top:0.25rem;left:0.25rem;z-index:10;min-width:1.5rem;padding:0 0.4rem;border-radius:1rem;background:#0d6efd;color:#fff;font:600 0.75rem/1.5rem system-ui,sans-serif;text-align:center;opacity:0;transition:opacity .15s}" +
                "[data-cms-section].cms-section--hover{outline:2px solid #0d6efd;outline-offset:2px}" +
                "[data-cms-section].cms-section--hover::before{opacity:1}" +
                ".cms-content-edit{position:absolute;top:0.25rem;right:0.25rem;z-index:11;padding:0.25rem 0.75rem;border:0;border-radius:0.375rem;background:" + accent() + ";color:#fff;font:600 0.8125rem/1.5 system-ui,sans-serif;cursor:pointer;opacity:0;transition:opacity .15s}" +
                "[data-cms-section]:hover>.cms-content-edit,.cms-section--hover>.cms-content-edit,.cms-content-edit:focus-visible{opacity:1}" +
                "[data-cms-section]:has(>.cms-content-edit):hover{outline:2px solid " + accent() + ";outline-offset:2px}";
            inner.head.appendChild(style);

            items.forEach(function (item) {
                var section = previewSection(item.getAttribute("data-section"));
                var rank = item.querySelector(".cms-content__item-rank");

                if (section && rank) {
                    section.setAttribute("data-cms-rank", rank.textContent.trim());
                    section.addEventListener("mouseenter", function () { item.classList.add("is-hover"); });
                    section.addEventListener("mouseleave", function () { item.classList.remove("is-hover"); });

                    // Hovering a section of the preview offers to edit it: the
                    // button opens its form in the list, where it is edited.
                    if (item.querySelector("[data-cms-edit]")) {
                        var button = inner.createElement("button");
                        button.type = "button";
                        button.className = "cms-content-edit";
                        button.setAttribute("data-testid", "cms-content-edit-" + item.getAttribute("data-section"));
                        button.textContent = labels.edit || "Edit";
                        button.addEventListener("click", function (event) {
                            event.preventDefault();
                            event.stopPropagation();
                            editSection(item);
                        });
                        section.appendChild(button);
                    }
                }
            });
        }

        // The form of a section opened from the preview: the panel is brought
        // to it and its first field takes the focus.
        function editSection(item) {
            var panel = item.querySelector("[data-cms-edit]");

            if (!panel) {
                return;
            }

            panel.open = true;
            item.scrollIntoView({ behavior: "smooth", block: "start" });
            focusFirstField(panel, 10);
        }

        // The rich-text editor is mounted when the panel opens, a moment
        // later: the first field is looked for until it shows.
        function focusFirstField(panel, tries) {
            var fields = panel.querySelectorAll(".ProseMirror, input:not([type=hidden]), textarea, select");

            for (var index = 0; index < fields.length; index++) {
                if (fields[index].offsetParent !== null) {
                    fields[index].focus({ preventScroll: true });

                    return;
                }
            }

            if (tries > 0) {
                setTimeout(function () { focusFirstField(panel, tries - 1); }, 100);
            }
        }

        // Adding a section happens where it lands: a button written into the
        // preview right after the last section, in the flow of the page. It
        // only exists in this frame: the page itself, and its preview opened
        // on its own, never carry it.
        var addDialog = root.querySelector("[data-cms-add-dialog]");

        function openAddDialog() {
            if (addDialog && !addDialog.open && !running) {
                addDialog.showModal();
            }
        }

        root.querySelectorAll("[data-cms-add-open]").forEach(function (button) {
            button.addEventListener("click", openAddDialog);
        });

        function addSlot() {
            var inner = previewDocument();
            var sections = inner ? inner.querySelectorAll("[data-cms-section]") : [];

            if (!addDialog || !sections.length || inner.getElementById("cms-content-add-slot")) {
                return;
            }

            var color = accent();
            var style = inner.createElement("style");
            style.textContent = "#cms-content-add-slot{display:flex;justify-content:center;width:min(75rem,100% - 2rem);margin:2rem auto;padding:1.25rem;border:2px dashed " + color + ";border-radius:.5rem;box-sizing:border-box}" +
                "#cms-content-add-slot button{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem 1rem;border:1px solid " + color + ";border-radius:.375rem;background:#fff;color:" + color + ";font:600 .875rem/1.5 system-ui,sans-serif;cursor:pointer}" +
                "#cms-content-add-slot button:hover,#cms-content-add-slot button:focus-visible{background:" + color + ";color:#fff}";
            inner.head.appendChild(style);

            var slot = inner.createElement("div");
            slot.id = "cms-content-add-slot";
            var button = inner.createElement("button");
            button.type = "button";
            button.setAttribute("data-testid", "cms-content-add-slot");
            button.textContent = "+ " + (labels.add || "Add a section");
            button.addEventListener("click", openAddDialog);
            slot.appendChild(button);

            var last = sections[sections.length - 1];
            last.parentNode.insertBefore(slot, last.nextSibling);
        }

        function revealLastSection() {
            var inner = previewDocument();
            var sections = inner ? inner.querySelectorAll("[data-cms-section]") : [];

            if (revealLast && sections.length) {
                revealLast = false;
                sections[sections.length - 1].scrollIntoView({ block: "start" });
            }
        }

        if (frame) {
            frame.addEventListener("load", markPreview);
            frame.addEventListener("load", addSlot);
            frame.addEventListener("load", revealLastSection);
            markPreview();
            addSlot();
        }

        items.forEach(function (item) {
            var id = item.getAttribute("data-section");

            function light(on) {
                var section = previewSection(id);

                if (section) {
                    section.classList.toggle("cms-section--hover", on);
                }
            }

            item.addEventListener("mouseenter", function () { light(true); });
            item.addEventListener("mouseleave", function () { light(false); });
            item.addEventListener("focusin", function () { light(true); });
            item.addEventListener("focusout", function () { light(false); });

            var locate = item.querySelector("[data-cms-locate]");

            if (locate) {
                locate.addEventListener("click", function () {
                    var section = previewSection(id);

                    if (section) {
                        section.scrollIntoView({ behavior: "smooth", block: "start" });
                    }
                });
            }
        });

        /* ---- Reordering by drag and drop -------------------------------- */

        // A section is dragged by its handle only: the whole item draggable
        // would steal the selection of its text and the clicks of its forms.
        // It moves in the list while it is dragged, and its new rank is
        // posted when it is dropped; the arrows stay the way to do it from
        // the keyboard.
        root.querySelectorAll("[data-cms-reorder]").forEach(function (list) {
            var dragged = null;
            var from = -1;

            function items() {
                return Array.prototype.slice.call(list.querySelectorAll(":scope > [data-cms-section-item]"));
            }

            list.querySelectorAll("[data-cms-grip]").forEach(function (grip) {
                var item = grip.closest("[data-cms-section-item]");

                grip.addEventListener("pointerdown", function () { item.draggable = true; });
                grip.addEventListener("pointerup", function () { item.draggable = false; });
            });

            list.addEventListener("dragstart", function (event) {
                var item = event.target.closest && event.target.closest("[data-cms-section-item]");

                if (!item || !item.draggable || running) {
                    event.preventDefault();

                    return;
                }

                dragged = item;
                from = items().indexOf(item);
                event.dataTransfer.effectAllowed = "move";
                event.dataTransfer.setData("text/plain", item.getAttribute("data-section"));
                item.classList.add("cms-content__item--dragged");
            });

            list.addEventListener("dragover", function (event) {
                if (!dragged) {
                    return;
                }

                event.preventDefault();
                var over = event.target.closest && event.target.closest("[data-cms-section-item]");

                if (!over || over === dragged) {
                    return;
                }

                var box = over.getBoundingClientRect();
                list.insertBefore(dragged, event.clientY < box.top + box.height / 2 ? over : over.nextSibling);
            });

            list.addEventListener("drop", function (event) {
                if (dragged) {
                    event.preventDefault();
                }
            });

            list.addEventListener("dragend", function () {
                if (!dragged) {
                    return;
                }

                var item = dragged;
                var to = items().indexOf(item);
                dragged = null;
                item.draggable = false;
                item.classList.remove("cms-content__item--dragged");

                if (to === from) {
                    return;
                }

                var body = new FormData();
                body.append("_token", list.getAttribute("data-cms-reorder-token"));
                body.append("section", item.getAttribute("data-section"));
                body.append("position", String(to));
                running = true;

                fetch(list.getAttribute("data-cms-reorder"), {
                    method: "POST",
                    body: body,
                    credentials: "same-origin",
                    redirect: "follow",
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    return response.text();
                }).then(swap).catch(function () {
                    window.location.reload();
                });
            });
        });

        /* ---- Editing by hand ------------------------------------------- */

        // A section saved by hand comes back with the screen the server
        // redirects to, drawn in place like the end of a generation. When
        // the answer cannot be read, the form is sent the plain way.
        root.querySelectorAll("form[data-cms-section-form], form[data-cms-remove], form[data-cms-move], form[data-cms-add]").forEach(function (form) {
            form.addEventListener("submit", function (event) {
                if (form.hasAttribute("data-cms-remove") && !window.confirm(labels.remove)) {
                    event.preventDefault();

                    return;
                }

                if (!window.fetch || running) {
                    return;
                }

                if (!form.reportValidity()) {
                    event.preventDefault();

                    return;
                }

                event.preventDefault();
                var button = form.querySelector("button[type=submit]");

                if (form.hasAttribute("data-cms-add")) {
                    form.closest("dialog").close();
                    revealLast = true;
                }

                if (button) {
                    button.disabled = true;
                }

                fetch(form.action, {
                    method: "POST",
                    body: new FormData(form),
                    credentials: "same-origin",
                    redirect: "follow",
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    return response.text();
                }).then(swap).catch(function () {
                    form.submit();
                });
            });
        });

        // The rich-text editor is mounted when a panel opens, not twenty times
        // at once; a module that is not there leaves the textarea as it is.
        root.querySelectorAll("[data-cms-edit]").forEach(function (panel) {
            panel.addEventListener("toggle", function () {
                if (!panel.open || panel.cmsMounted) {
                    return;
                }

                panel.cmsMounted = true;

                if (window.TiptapEditor && typeof window.TiptapEditor.mount === "function") {
                    panel.querySelectorAll("textarea[data-cms-html]").forEach(function (textarea) {
                        window.TiptapEditor.mount(textarea);
                        nameTheEditor(textarea, 10);
                    });
                }
            });
        });

        // The editor replaces the textarea with an editable area the label of the
        // field no longer points at: it is named after that label once mounted.
        function nameTheEditor(textarea, tries) {
            var label = textarea.labels && textarea.labels[0] ? textarea.labels[0].textContent.trim() : "";
            var holder = textarea.closest(".mb-3") || textarea.parentElement;
            var area = holder ? holder.querySelector(".ProseMirror") : null;

            if (area) {
                if (label && !area.hasAttribute("aria-label")) {
                    area.setAttribute("aria-label", label);
                }

                return;
            }

            if (tries > 0) {
                setTimeout(function () { nameTheEditor(textarea, tries - 1); }, 300);
            }
        }

        // Rows of a list prop, from the prototype of the collection.
        root.querySelectorAll("[data-cms-items]").forEach(function (list) {
            var rows = list.querySelector("[data-cms-items-list]");
            var add = list.querySelector("[data-cms-items-add]");
            var prototype = list.getAttribute("data-prototype");
            var max = parseInt(list.getAttribute("data-cms-items-max"), 10) || 12;
            var min = parseInt(list.getAttribute("data-cms-items-min"), 10) || 0;
            var counter = rows.children.length;

            function refresh() {
                var count = rows.children.length;
                add.hidden = count >= max;
                rows.querySelectorAll(":scope > [data-cms-item] > [data-cms-item-remove]").forEach(function (button) {
                    button.hidden = count <= min;
                });
            }

            add.addEventListener("click", function () {
                var holder = document.createElement("template");
                holder.innerHTML = prototype.replace(/__name__/g, String(counter++)).trim();
                rows.appendChild(holder.content.firstElementChild);
                refresh();
                rows.lastElementChild.querySelector("input, textarea, select")?.focus();
            });

            rows.addEventListener("click", function (event) {
                var remove = event.target.closest("[data-cms-item-remove]");

                if (remove) {
                    remove.closest("[data-cms-item]").remove();
                    refresh();
                }
            });

            refresh();
        });

        // An image field shows the picture it holds: the one chosen in the
        // library, the file about to replace it, or the address typed for it.
        root.querySelectorAll("select[data-cms-image]").forEach(function (select) {
            var holder = select.closest(".mb-3") || select.parentElement;
            var panel = holder ? holder.parentElement : null;
            var preview = document.createElement("img");
            preview.className = "cms-content__image-preview";
            preview.alt = "";
            preview.hidden = true;
            holder.insertBefore(preview, select);

            function show(url) {
                preview.hidden = !url;

                if (url) {
                    preview.src = url;
                }
            }

            function fromSelect() {
                var option = select.options[select.selectedIndex];
                show(option ? option.getAttribute("data-url") : null);
            }

            var address = panel ? panel.querySelector('input[name="' + select.name.replace(/\]$/, "__url]") + '"]') : null;
            var file = panel ? panel.querySelector('input[name="' + select.name.replace(/\]$/, "__file]") + '"]') : null;

            select.addEventListener("change", function () {
                if (address) { address.value = ""; }
                fromSelect();
            });

            if (address) {
                address.addEventListener("input", function () {
                    if (address.value.trim() !== "") {
                        show(address.value.trim());
                    } else {
                        fromSelect();
                    }
                });
            }

            if (file) {
                file.addEventListener("change", function () {
                    if (file.files && file.files[0]) {
                        show(URL.createObjectURL(file.files[0]));
                    } else {
                        fromSelect();
                    }
                });
            }

            if (address && address.value.trim() !== "") {
                show(address.value.trim());
            } else {
                fromSelect();
            }
        });

        // A reference is picked from the list its source answers: a select for a
        // short list, a search box for a long one. The number field stays
        // underneath for whoever wants to type an id.
        root.querySelectorAll("input[data-cms-picker]").forEach(function (input) {
            var source = input.getAttribute("data-cms-picker");
            var searchable = input.hasAttribute("data-cms-picker-searchable");
            var picker = document.createElement(searchable ? "input" : "select");
            picker.className = "form-select form-select-sm mb-1";
            picker.setAttribute("aria-label", input.labels && input.labels[0] ? input.labels[0].textContent.trim() : "");

            function fill(rows) {
                picker.replaceChildren();
                var none = document.createElement("option");
                none.value = "";
                none.textContent = "—";
                picker.appendChild(none);
                rows.forEach(function (row) {
                    var option = document.createElement("option");
                    option.value = String(row.id);
                    option.textContent = row.name;
                    option.selected = String(row.id) === input.value;
                    picker.appendChild(option);
                });
            }

            if (searchable) {
                var datalist = document.createElement("datalist");
                datalist.id = input.id + "_options";
                picker.setAttribute("list", datalist.id);
                picker.className = "form-control form-control-sm mb-1";
                picker.placeholder = labels.search || "Search…";
                var found = {};
                picker.addEventListener("input", function () {
                    var chosen = found[picker.value];

                    if (chosen) {
                        input.value = String(chosen);

                        return;
                    }

                    if (picker.value.length < 2) {
                        return;
                    }

                    fetch(source + "?q=" + encodeURIComponent(picker.value), { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
                        .then(function (response) { return response.json(); })
                        .then(function (rows) {
                            datalist.replaceChildren();
                            found = {};
                            rows.forEach(function (row) {
                                found[row.name] = row.id;
                                var option = document.createElement("option");
                                option.value = row.name;
                                datalist.appendChild(option);
                            });
                        })
                        .catch(function () {});
                });
                input.insertAdjacentElement("beforebegin", picker);
                input.insertAdjacentElement("beforebegin", datalist);

                if (input.value) {
                    fetch(source + "?id=" + encodeURIComponent(input.value), { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
                        .then(function (response) { return response.json(); })
                        .then(function (row) { if (row && row.name) { picker.value = row.name; found[row.name] = row.id; } })
                        .catch(function () {});
                }
            } else {
                picker.addEventListener("change", function () {
                    input.value = picker.value;
                });
                input.insertAdjacentElement("beforebegin", picker);
                fetch(source, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
                    .then(function (response) { return response.json(); })
                    .then(fill)
                    .catch(function () { picker.remove(); });
            }
        });

    }

    enhance(first);

    window.addEventListener("beforeunload", function (event) {
        if (running) {
            event.preventDefault();
            event.returnValue = labels.leaving;
        }
    });
})();
