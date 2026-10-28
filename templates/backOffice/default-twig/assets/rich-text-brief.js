/*
 * "Write from a brief" beside the rich-text fields of a page of the CMS and of
 * the items of the catalogue.
 *
 * The hook of the module gives, per screen, the fields served and where the
 * form keeps the title, the id and the language of the object. A button by the
 * label of each field opens a dialog: the brief goes to the server, which
 * writes the text with the generator and answers it as formatted text, already
 * filtered. The editor reads it, then replaces the text of the field, inserts
 * it at the cursor, or leaves it. Nothing is saved: the form saves the field as
 * if the text had been typed.
 *
 * The rich-text editor (the Tiptap module) publishes no instance: it is read on
 * the editable element it draws, where Tiptap leaves it. Without it, the field
 * is a plain textarea and is written as one.
 */
(function () {
    'use strict';

    function backOfficeToken() {
        var meta = document.querySelector('meta[name="bo-token"]');

        return meta ? meta.getAttribute('content') || '' : '';
    }

    function fieldNamed(name) {
        return name ? document.querySelector('[name="' + name.replace(/"/g, '\\"') + '"]') : null;
    }

    function valueOf(name, fallback) {
        var field = fieldNamed(name);

        return field ? field.value : fallback;
    }

    // The Tiptap editor drawn for this textarea, once it is mounted.
    function editorOf(textarea) {
        var wrapper = textarea.closest('.tiptap-wrapper');
        var area = wrapper ? wrapper.querySelector('.tiptap-prose, .ProseMirror') : null;

        return area && area.editor && area.editor.commands ? area.editor : null;
    }

    function syncTextarea(textarea, html) {
        textarea.value = html;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function element(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text) {
            node.textContent = text;
        }

        return node;
    }

    function bind(config) {
        var m = config.messages || {};
        var dialog = null;
        var active = null;

        function buildDialog() {
            dialog = element('dialog', 'cms-text-brief-dialog border-0 rounded shadow p-0');
            dialog.setAttribute('data-cms-text-brief-dialog', '');
            dialog.style.width = 'min(720px, calc(100vw - 32px))';

            var body = element('div', 'p-4');
            var heading = element('h2', 'h5 mb-3', m.title);
            heading.id = 'cms-text-brief-title';
            dialog.setAttribute('aria-labelledby', heading.id);

            var briefLabel = element('label', 'form-label', m.brief);
            briefLabel.htmlFor = 'cms-text-brief-text';
            var brief = element('textarea', 'form-control');
            brief.id = 'cms-text-brief-text';
            brief.rows = 4;
            brief.setAttribute('data-cms-text-brief-text', '');
            var help = element('div', 'form-text mb-3', m.briefHelp);

            var row = element('div', 'row g-2 mb-3');
            var tone = element('input', 'form-control');
            tone.placeholder = m.tone;
            tone.setAttribute('aria-label', m.tone);
            var audience = element('input', 'form-control');
            audience.placeholder = m.audience;
            audience.setAttribute('aria-label', m.audience);
            [tone, audience].forEach(function (input) {
                var col = element('div', 'col-sm-6');
                col.appendChild(input);
                row.appendChild(col);
            });

            var status = element('div', 'alert d-none');
            status.setAttribute('role', 'status');
            status.setAttribute('data-cms-text-brief-status', '');

            var previewLabel = element('div', 'small text-muted mb-1 d-none', m.preview);
            var preview = element('div', 'border rounded p-3 mb-2 d-none');
            preview.setAttribute('data-cms-text-brief-preview', '');
            preview.style.maxHeight = '40vh';
            preview.style.overflow = 'auto';
            var cost = element('div', 'small text-muted mb-3 d-none');

            var actions = element('div', 'd-flex flex-wrap gap-2 justify-content-end');
            var write = element('button', 'btn btn-primary', m.write);
            write.type = 'button';
            write.setAttribute('data-cms-text-brief-write', '');
            var replace = element('button', 'btn btn-primary d-none', m.replace);
            replace.type = 'button';
            replace.setAttribute('data-cms-text-brief-replace', '');
            var insert = element('button', 'btn btn-outline-primary d-none', m.insert);
            insert.type = 'button';
            insert.setAttribute('data-cms-text-brief-insert', '');
            var cancel = element('button', 'btn btn-outline-secondary', m.cancel);
            cancel.type = 'button';
            actions.append(cancel, insert, replace, write);

            body.append(heading, briefLabel, brief, help, row, status, previewLabel, preview, cost, actions);
            dialog.appendChild(body);
            document.body.appendChild(dialog);

            var written = '';

            function show(node, visible) {
                node.classList.toggle('d-none', !visible);
            }

            function say(text, kind) {
                status.textContent = text || '';
                status.className = 'alert ' + (text ? 'alert-' + kind : 'd-none');
            }

            dialog.reset = function () {
                written = '';
                preview.innerHTML = '';
                [preview, previewLabel, cost, replace, insert].forEach(function (node) { show(node, false); });
                write.textContent = m.write;
                write.disabled = false;
                say('');
            };

            cancel.addEventListener('click', function () { dialog.close(); });

            write.addEventListener('click', function () {
                if (brief.value.trim() === '') {
                    brief.focus();

                    return;
                }

                var editor = editorOf(active.textarea);
                var body = new FormData();
                body.append('type', config.type);
                body.append('id', config.idField ? valueOf(config.idField, '0') : String(config.id || 0));
                body.append('locale', config.localeField ? valueOf(config.localeField, '') : (config.locale || ''));
                body.append('field', active.kind);
                body.append('title', valueOf(config.title, ''));
                body.append('brief', brief.value);
                body.append('tone', tone.value);
                body.append('audience', audience.value);
                body.append('current', editor ? editor.getHTML() : active.textarea.value);
                // A write route of the module: the token of the back office,
                // in the body, never in the address.
                body.append('_token', backOfficeToken());

                write.disabled = true;
                write.textContent = m.writing;
                say('');

                fetch(config.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    body: body,
                })
                    .then(function (response) {
                        return response.json().catch(function () { return { error: m.failed }; });
                    })
                    .then(function (payload) {
                        write.disabled = false;
                        write.textContent = m.again;

                        if (!payload || payload.error || typeof payload.html !== 'string') {
                            say((payload && payload.error) || m.failed, 'danger');

                            return;
                        }

                        // Formatted text the server filtered: paragraphs,
                        // emphasis, lists, links, subheadings, quotes.
                        written = payload.html;
                        preview.innerHTML = written;
                        cost.textContent = (m.cost || '').replace('%cost%', payload.cost);
                        [preview, previewLabel, cost, replace, insert].forEach(function (node) { show(node, true); });
                    })
                    .catch(function (error) {
                        console.error('TheliaCMS: the text written from the brief could not be shown', error);
                        write.disabled = false;
                        write.textContent = m.again;
                        say(m.failed, 'danger');
                    });
            });

            function put(mode) {
                var textarea = active.textarea;
                var editor = editorOf(textarea);

                if (!editor && textarea.closest('.tiptap-wrapper')) {
                    say(m.noEditor, 'warning');

                    return;
                }

                if (editor) {
                    if (mode === 'replace') {
                        editor.commands.setContent(written);
                    } else {
                        editor.chain().focus().insertContent(written).run();
                    }

                    syncTextarea(textarea, editor.getHTML());
                } else {
                    syncTextarea(textarea, mode === 'replace' ? written : textarea.value + written);
                }

                dialog.close();
            }

            replace.addEventListener('click', function () { put('replace'); });
            insert.addEventListener('click', function () { put('insert'); });
        }

        config.fields.forEach(function (field) {
            var textarea = fieldNamed(field.name);

            if (!textarea || textarea.tagName !== 'TEXTAREA') {
                return;
            }

            var button = element('button', 'btn btn-sm btn-outline-secondary ms-2', m.button);
            button.type = 'button';
            button.setAttribute('data-cms-text-brief-open', field.kind);

            var label = textarea.labels && textarea.labels[0];

            if (label) {
                label.insertAdjacentElement('afterend', button);
            } else {
                textarea.insertAdjacentElement('beforebegin', button);
            }

            button.addEventListener('click', function () {
                if (!dialog) {
                    buildDialog();
                }

                active = { textarea: textarea, kind: field.kind };
                dialog.reset();
                dialog.showModal();
                dialog.querySelector('[data-cms-text-brief-text]').focus();
            });
        });
    }

    function start() {
        document.querySelectorAll('script[data-cms-text-brief-config]').forEach(function (node) {
            try {
                bind(JSON.parse(node.textContent));
            } catch (error) {
                // A screen without the fields served, or a config cut short:
                // the fields stay as they are.
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
