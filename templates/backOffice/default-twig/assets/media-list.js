/*
 * The selection of the media list: a box to select the images of the page,
 * how many are selected, the tag field shown only for an action on tags,
 * and a dialog of the screen before deleting. The form works without it.
 */
(function () {
    'use strict';

    function start() {
        var form = document.querySelector('form[data-cms-media-bulk]');

        if (!form) {
            return;
        }

        var boxes = Array.prototype.slice.call(document.querySelectorAll('input[data-cms-media-select]'));
        var all = form.querySelector('[data-cms-media-select-all]');
        var count = form.querySelector('[data-cms-media-selected]');
        var action = form.querySelector('select[name="action"]');
        var tag = form.querySelector('[data-cms-media-bulk-tag]');
        var apply = form.querySelector('[data-cms-media-bulk-apply]');
        var dialog = document.querySelector('[data-cms-media-bulk-dialog]');
        var label = count.textContent;
        var confirmed = false;

        function refresh() {
            var selected = boxes.filter(function (box) { return box.checked; }).length;
            all.checked = selected > 0 && selected === boxes.length;
            all.indeterminate = selected > 0 && selected < boxes.length;
            count.textContent = selected > 0 ? label + ' (' + selected + ')' : label;
            apply.disabled = selected === 0 || action.value === '';
            tag.hidden = action.value !== 'tag-add' && action.value !== 'tag-remove';
            tag.required = !tag.hidden;
        }

        all.hidden = false;
        count.hidden = false;
        all.addEventListener('change', function () {
            boxes.forEach(function (box) { box.checked = all.checked; });
            refresh();
        });
        boxes.forEach(function (box) { box.addEventListener('change', refresh); });
        action.addEventListener('change', refresh);

        form.addEventListener('submit', function (event) {
            if (action.value !== 'delete' || confirmed || !dialog || typeof dialog.showModal !== 'function') {
                return;
            }

            event.preventDefault();
            dialog.showModal();
        });

        if (dialog) {
            dialog.querySelector('[data-cms-media-bulk-cancel]').addEventListener('click', function () { dialog.close(); });
            dialog.querySelector('[data-cms-media-bulk-confirm]').addEventListener('click', function () {
                confirmed = true;
                dialog.close();
                form.requestSubmit();
            });
        }

        refresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
