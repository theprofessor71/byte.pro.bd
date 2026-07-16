/* admin.js — CSP-safe wiring for admin pages:
   [data-confirm] → confirm() guard on submit buttons,
   [data-select-on-click] → select input text,
   [data-copy-on-click] → select + copy to clipboard. */
(function () {
    'use strict';

    document.addEventListener('click', function (ev) {
        var btn = ev.target.closest('[data-confirm]');
        if (btn && !window.confirm(btn.dataset.confirm)) {
            ev.preventDefault();
            ev.stopImmediatePropagation();
        }
        var sel = ev.target.closest('[data-select-on-click]');
        if (sel && typeof sel.select === 'function') {
            sel.select();
        }
        var cp = ev.target.closest('[data-copy-on-click]');
        if (cp && typeof cp.select === 'function') {
            cp.select();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(cp.value).catch(function () {});
            }
        }
    }, true);
})();
