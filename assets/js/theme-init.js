/* theme-init.js — runs synchronously before paint to avoid theme flash.
   External file (not inline) so it passes CSP script-src 'self'. */
(function () {
    try {
        var t = localStorage.getItem('cb-theme');
        if (t === 'light' || t === 'dark') {
            document.documentElement.dataset.theme = t;
        }
        var a = localStorage.getItem('cb-accent');
        if (a && /^[a-z]{1,10}$/.test(a)) {
            document.documentElement.dataset.accent = a;
        }
    } catch (e) {}
})();
