/* utils.js — toast, lightbox, bookmarks, clipboard (shared helpers) */
(function () {
    'use strict';

    /* ---------- Toast ---------- */
    window.cbToast = function (msg, type) {
        var host = document.getElementById('toastHost');
        if (!host) return;
        var t = document.createElement('div');
        t.className = 'toast ' + (type || '');
        t.textContent = msg;
        host.appendChild(t);
        setTimeout(function () {
            t.style.opacity = '0';
            t.style.transition = 'opacity .3s';
            setTimeout(function () { t.remove(); }, 320);
        }, 2600);
    };

    /* ---------- Clipboard ---------- */
    window.cbCopy = function (text, okMsg) {
        function done() { cbToast(okMsg || 'Copied to clipboard', 'ok'); }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, fallback);
        } else { fallback(); }
        function fallback() {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) {}
            ta.remove();
        }
    };

    /* ---------- Lightbox ---------- */
    var lb = document.getElementById('lightbox');
    if (lb) {
        var lbImg = lb.querySelector('img');
        document.addEventListener('click', function (ev) {
            var img = ev.target.closest('.post-content img.post-img, .post-hero-img');
            if (img) {
                lbImg.src = img.src;
                lbImg.alt = img.alt || '';
                lb.hidden = false;
                document.body.style.overflow = 'hidden';
            } else if (ev.target === lb || ev.target.closest('.lightbox-close')) {
                lb.hidden = true;
                document.body.style.overflow = '';
            }
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && !lb.hidden) { lb.hidden = true; document.body.style.overflow = ''; }
        });
    }

    /* ---------- Bookmarks ---------- */
    function readBookmarks() {
        try { return JSON.parse(localStorage.getItem('cb-bookmarks') || '[]'); } catch (e) { return []; }
    }
    window.cbBookmarks = {
        list: readBookmarks,
        has: function (slug) { return readBookmarks().some(function (b) { return b.slug === slug; }); },
        toggle: function (slug, title) {
            var items = readBookmarks();
            var idx = items.findIndex(function (b) { return b.slug === slug; });
            if (idx >= 0) { items.splice(idx, 1); } else { items.push({ slug: slug, title: title }); }
            localStorage.setItem('cb-bookmarks', JSON.stringify(items));
            return idx < 0; // true = now bookmarked
        }
    };
})();
