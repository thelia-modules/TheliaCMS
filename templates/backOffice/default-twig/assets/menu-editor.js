/*
 * Menu editor of the Thelia CMS back office.
 *
 * Two jobs, both pure progressive enhancement: show only the target field that
 * applies to the chosen kind of entry, and let rows be dragged around the tree.
 * Everything it does can also be done without it — the fields it hides are
 * simply all visible, and the tree has move buttons and a "nested under" select.
 * That is deliberate: dragging is not operable with a keyboard, so it can never
 * be the only way to reorder a menu.
 *
 * Plain script rather than a Stimulus controller: the back-office application is
 * built by the theme and a module cannot register a controller with it, and one
 * screen is not worth a bundler of its own.
 */
(function () {
    'use strict';

    function bindTargetFields(select) {
        var form = select.closest('form');

        if (!form) {
            return;
        }

        var fields = form.querySelectorAll('[data-cms-menu-target-field]');

        function apply() {
            fields.forEach(function (field) {
                // Hidden fields keep their value; the server only reads the one
                // that matches the kind actually chosen.
                field.hidden = field.getAttribute('data-cms-menu-target-field') !== select.value;
            });
        }

        select.addEventListener('change', apply);
        apply();
    }

    function bindTree(tree) {
        var dragged = null;
        var dropMode = 'sibling';

        function rows() {
            return Array.prototype.slice.call(tree.querySelectorAll('tr[data-row-id]'));
        }

        function clearMarks() {
            rows().forEach(function (row) {
                row.classList.remove('is-drop-target', 'is-drop-child');
            });
        }

        tree.addEventListener('dragstart', function (event) {
            var row = event.target.closest('tr[data-row-id]');

            if (!row) {
                return;
            }

            dragged = row;
            row.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', row.dataset.rowId);
        });

        tree.addEventListener('dragover', function (event) {
            var row = event.target.closest('tr[data-row-id]');

            if (!row || !dragged || row === dragged) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            clearMarks();

            // Dropping on the right third of a row files the entry *under* it,
            // which is how nesting is done with a mouse.
            var bounds = row.getBoundingClientRect();
            dropMode = event.clientX - bounds.left > bounds.width * 0.66 ? 'child' : 'sibling';
            row.classList.add(dropMode === 'child' ? 'is-drop-child' : 'is-drop-target');
        });

        tree.addEventListener('dragleave', clearMarks);

        tree.addEventListener('dragend', function () {
            if (dragged) {
                dragged.classList.remove('is-dragging');
                dragged = null;
            }

            clearMarks();
        });

        tree.addEventListener('drop', function (event) {
            var target = event.target.closest('tr[data-row-id]');

            if (!target || !dragged || target === dragged) {
                return;
            }

            event.preventDefault();
            clearMarks();

            var parent;
            var position;

            if (dropMode === 'child') {
                parent = target.dataset.rowId;
                position = 1;
            } else {
                parent = target.dataset.parent;
                position = siblingPosition(target, parent);
            }

            place(dragged.dataset.rowId, parent, position);
        });

        function siblingPosition(target, parent) {
            var siblings = rows().filter(function (row) {
                return row.dataset.parent === parent && row !== dragged;
            });

            return siblings.indexOf(target) + 1;
        }

        function place(id, parent, position) {
            var body = new FormData();
            body.append('parent', parent);
            body.append('position', String(position));

            fetch(tree.dataset.placeUrl.replace('{id}', id), {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                body: body,
            })
                .then(function (response) {
                    return response.json().catch(function () {
                        return {};
                    });
                })
                .then(function (payload) {
                    if (payload && payload.error) {
                        window.alert(payload.error);
                    }

                    // The server owns the tree: reload rather than guess what it
                    // made of the move.
                    window.location.reload();
                })
                .catch(function () {
                    window.location.reload();
                });
        }
    }

    function start() {
        document.querySelectorAll('[data-cms-menu-target-select]').forEach(bindTargetFields);
        document.querySelectorAll('[data-cms-menu-tree]').forEach(bindTree);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
