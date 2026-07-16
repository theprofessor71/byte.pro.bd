/* search.js — Ctrl+K modal with debounced AJAX search against search.php?format=json */
(function () {
    'use strict';

    var modal = document.getElementById('searchModal');
    var input = document.getElementById('searchInput');
    var results = document.getElementById('searchResults');
    var btn = document.getElementById('searchBtn');
    if (!modal || !input || !results) return;

    var selected = -1;
    var controller = null;
    var debounceTimer = null;

    window.cbOpenSearch = function () {
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        input.value = '';
        results.innerHTML = hint();
        selected = -1;
        setTimeout(function () { input.focus(); }, 30);
    };
    function close() {
        modal.hidden = true;
        document.body.style.overflow = '';
    }
    function hint() {
        return '<div class="search-empty">Type at least 2 characters… <span class="muted">(↑↓ navigate · Enter open · Esc close)</span></div>';
    }

    if (btn) btn.addEventListener('click', cbOpenSearch);
    modal.addEventListener('click', function (ev) { if (ev.target === modal) close(); });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && !modal.hidden) close();
    });

    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var q = input.value.trim();
        if (q.length < 2) { results.innerHTML = hint(); return; }
        debounceTimer = setTimeout(function () { run(q); }, 250);
    });

    function run(q) {
        if (controller) controller.abort();
        controller = new AbortController();
        fetch('/search.php?format=json&q=' + encodeURIComponent(q), { signal: controller.signal })
            .then(function (r) { return r.json(); })
            .then(function (data) { render(data); })
            .catch(function (e) { if (e.name !== 'AbortError') results.innerHTML = '<div class="search-empty">Search failed — try again.</div>'; });
    }

    function render(data) {
        selected = -1;
        if (!data.results || !data.results.length) {
            results.innerHTML = '<div class="search-empty">No results for “' + esc(data.query) + '”.</div>';
            return;
        }
        results.innerHTML = '';
        data.results.forEach(function (r) {
            var a = document.createElement('a');
            a.className = 'search-result';
            a.href = r.url;
            a.innerHTML = '<div class="sr-title">' + esc(r.title) + '</div>'
                + '<div class="sr-meta">' + esc(r.category) + ' — ' + esc(r.excerpt) + '</div>';
            results.appendChild(a);
        });
    }

    input.addEventListener('keydown', function (ev) {
        var items = results.querySelectorAll('.search-result');
        if (!items.length) return;
        if (ev.key === 'ArrowDown') { ev.preventDefault(); selected = Math.min(selected + 1, items.length - 1); }
        else if (ev.key === 'ArrowUp') { ev.preventDefault(); selected = Math.max(selected - 1, 0); }
        else if (ev.key === 'Enter') { ev.preventDefault(); items[selected >= 0 ? selected : 0].click(); return; }
        else return;
        items.forEach(function (el, i) { el.classList.toggle('selected', i === selected); });
        if (items[selected]) items[selected].scrollIntoView({ block: 'nearest' });
    });

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = String(s == null ? '' : s);
        return d.innerHTML;
    }
})();
