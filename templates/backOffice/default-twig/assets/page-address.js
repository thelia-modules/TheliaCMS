/*
 * Slug field of the page edit screen.
 *
 * Asks the server for the address the page would get from what the form holds
 * now (title, slug, parent, language of the screen) and shows it under the
 * field, with a button that fills the slug from the title and a notice when the
 * slug no longer follows the title. The server computes the address and writes
 * nothing: it changes when the form is saved, never before, so a published
 * address only moves on purpose.
 *
 * Progressive enhancement, as the menu editor: without the script the field is
 * a plain input, and an emptied field still takes the title on save.
 */
(function () {
    'use strict';

    function backOfficeToken() {
        var meta = document.querySelector('meta[name="bo-token"]');

        return meta ? meta.getAttribute('content') || '' : '';
    }

    function bind(input) {
        var messages = JSON.parse(input.getAttribute('data-messages') || '{}');
        var title = document.getElementById(input.getAttribute('data-title-field'));
        var parent = document.getElementById(input.getAttribute('data-parent-field'));
        var titleSegment = null;
        var pending = null;
        var sent = 0;

        var panel = document.createElement('div');
        panel.className = 'd-flex flex-wrap align-items-center gap-2 mt-2';
        panel.setAttribute('data-cms-page-address-panel', '');

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary';
        button.textContent = messages.regenerate || 'Regenerate from the title';

        var address = document.createElement('code');
        address.className = 'text-break';
        address.setAttribute('data-cms-page-address-preview', '');

        var notice = document.createElement('div');
        notice.className = 'form-text w-100';
        notice.setAttribute('role', 'status');
        notice.setAttribute('aria-live', 'polite');

        panel.appendChild(button);
        panel.appendChild(address);
        panel.appendChild(notice);
        input.insertAdjacentElement('afterend', panel);

        function render(payload) {
            if (payload.error) {
                address.textContent = '';
                notice.textContent = payload.error;
                notice.classList.add('text-danger');
                button.classList.remove('btn-warning');

                return;
            }

            titleSegment = payload.title_segment;
            address.textContent = (messages.address || 'Address: %address%').replace('%address%', '/' + payload.address);
            notice.classList.remove('text-danger');

            var lines = [];

            if (payload.suffixed) {
                lines.push(messages.suffixed);
            }

            // The slug typed differs from the one the title gives: the address
            // was edited by hand, or the page was renamed since. Pointed out,
            // never changed without a click.
            var stale = input.value.trim() !== '' && titleSegment !== null && titleSegment !== input.value.trim();

            if (stale) {
                lines.push(messages.stale);
            }

            notice.textContent = lines.join(' ');
            button.classList.toggle('btn-warning', stale);
            button.classList.toggle('btn-outline-secondary', !stale);
        }

        function ask(slug) {
            var body = new FormData();
            body.append('page', input.getAttribute('data-page-id') || '0');
            body.append('title', title ? title.value : '');
            body.append('slug', slug);
            body.append('parent', parent ? parent.value : '0');
            // A write route of the module, guarded by the token of the back
            // office even though it writes nothing; in the body, never the URL.
            body.append('_token', backOfficeToken());

            var ticket = ++sent;

            return fetch(input.getAttribute('data-proposal-url'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                body: body,
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    // An answer to a request overtaken by a later one is dropped.
                    return ticket === sent ? payload : null;
                })
                .catch(function () {
                    return ticket === sent ? { error: messages.failed } : null;
                });
        }

        function refresh() {
            ask(input.value.trim()).then(function (payload) {
                if (payload) {
                    render(payload);
                }
            });
        }

        function later() {
            window.clearTimeout(pending);
            pending = window.setTimeout(refresh, 300);
        }

        button.addEventListener('click', function () {
            ask('').then(function (payload) {
                if (!payload) {
                    return;
                }

                if (!payload.error) {
                    input.value = payload.segment;
                }

                render(payload);
            });
        });

        input.addEventListener('input', later);

        if (title) {
            title.addEventListener('input', later);
        }

        if (parent) {
            parent.addEventListener('change', refresh);
        }

        if ((title && title.value.trim() !== '') || input.value.trim() !== '') {
            refresh();
        }
    }

    function start() {
        document.querySelectorAll('input[data-cms-page-address]').forEach(bind);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
