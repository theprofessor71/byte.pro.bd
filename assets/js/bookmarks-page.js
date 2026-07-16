/* bookmarks-page.js — renders localStorage bookmarks on bookmarks.html.
   External file (not inline) to stay consistent with CSP script-src 'self'. */
(function () {
    'use strict';

    var list = document.getElementById('bmList');
    var empty = document.getElementById('bmEmpty');
    var clearBtn = document.getElementById('bmClear');
    if (!list) return;

    var items = [];
    try { items = JSON.parse(localStorage.getItem('cb-bookmarks') || '[]'); } catch (e) {}

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }
    function render() {
        list.innerHTML = '';
        empty.hidden = items.length > 0;
        clearBtn.hidden = items.length === 0;
        items.forEach(function (b, i) {
            if (!b || typeof b.slug !== 'string' || !/^[a-z0-9-]+$/.test(b.slug)) return;
            var li = document.createElement('li');
            li.className = 'glass bookmark-item';
            li.innerHTML = '<a href="/post/' + esc(b.slug) + '">' + esc(b.title || b.slug) + '</a>'
                + '<button class="icon-btn" data-i="' + i + '" title="Remove">✕</button>';
            list.appendChild(li);
        });
    }
    list.addEventListener('click', function (ev) {
        var btn = ev.target.closest('button[data-i]');
        if (!btn) return;
        items.splice(Number(btn.dataset.i), 1);
        localStorage.setItem('cb-bookmarks', JSON.stringify(items));
        render();
    });
    clearBtn.addEventListener('click', function () {
        if (!confirm('Remove all bookmarks?')) return;
        items = [];
        localStorage.removeItem('cb-bookmarks');
        render();
    });
    render();
})();
