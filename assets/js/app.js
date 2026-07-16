/* app.js — core: preloader, theme, nav, progress bar, back-to-top,
   scroll reveal (AOS-lite), TOC highlight, keyboard shortcuts */
(function () {
    'use strict';

    /* ---------- Preloader ---------- */
    var pre = document.getElementById('preloader');
    if (pre) {
        window.addEventListener('load', function () { pre.classList.add('done'); });
        setTimeout(function () { pre.classList.add('done'); }, 1800); // never trap the page
    }

    /* ---------- Theme toggle ---------- */
    var themeBtn = document.getElementById('themeToggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', function () {
            var root = document.documentElement;
            var next = root.dataset.theme === 'light' ? 'dark' : 'light';
            root.dataset.theme = next;
            try { localStorage.setItem('cb-theme', next); } catch (e) {}
        });
    }

    /* ---------- Mobile nav ---------- */
    var navToggle = document.getElementById('navToggle');
    var nav = document.getElementById('mainNav');
    if (navToggle && nav) {
        navToggle.addEventListener('click', function () { nav.classList.toggle('open'); });
    }

    /* ---------- Reading progress bar ---------- */
    var bar = document.getElementById('progressBar');
    if (bar) {
        var ticking = false;
        window.addEventListener('scroll', function () {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(function () {
                var h = document.documentElement;
                var max = h.scrollHeight - h.clientHeight;
                bar.style.width = (max > 0 ? (h.scrollTop / max) * 100 : 0) + '%';
                ticking = false;
            });
        }, { passive: true });
    }

    /* ---------- Back to top ---------- */
    var btt = document.getElementById('backToTop');
    if (btt) {
        window.addEventListener('scroll', function () {
            btt.hidden = window.scrollY < 500;
        }, { passive: true });
        btt.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    }

    /* ---------- Scroll reveal (AOS-lite) ---------- */
    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting) { en.target.classList.add('aos-in'); io.unobserve(en.target); }
            });
        }, { threshold: 0.12 });
        document.querySelectorAll('[data-aos]').forEach(function (el) { io.observe(el); });
    } else {
        document.querySelectorAll('[data-aos]').forEach(function (el) { el.classList.add('aos-in'); });
    }

    /* ---------- TOC active highlight ---------- */
    var toc = document.getElementById('toc');
    if (toc && 'IntersectionObserver' in window) {
        var links = {};
        toc.querySelectorAll('a[href^="#"]').forEach(function (a) {
            links[a.getAttribute('href').slice(1)] = a;
        });
        var hio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (en.isIntersecting && links[en.target.id]) {
                    toc.querySelectorAll('a').forEach(function (a) { a.classList.remove('active'); });
                    links[en.target.id].classList.add('active');
                }
            });
        }, { rootMargin: '-80px 0px -70% 0px' });
        Object.keys(links).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) hio.observe(el);
        });
    }

    /* ---------- Accent color picker ---------- */
    var accentBtn = document.getElementById('accentBtn');
    var accentMenu = document.getElementById('accentMenu');
    if (accentBtn && accentMenu) {
        accentBtn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            accentMenu.hidden = !accentMenu.hidden;
        });
        accentMenu.addEventListener('click', function (ev) {
            var b = ev.target.closest('button[data-accent]');
            if (!b) return;
            var a = b.dataset.accent;
            if (a) {
                document.documentElement.dataset.accent = a;
                try { localStorage.setItem('cb-accent', a); } catch (e) {}
            } else {
                delete document.documentElement.dataset.accent;
                try { localStorage.removeItem('cb-accent'); } catch (e) {}
            }
            accentMenu.hidden = true;
        });
        document.addEventListener('click', function () { accentMenu.hidden = true; });
    }

    /* ---------- Shortcut help modal (?) ---------- */
    var scModal = document.getElementById('shortcutModal');
    var scClose = document.getElementById('shortcutClose');
    function toggleShortcuts(show) {
        if (scModal) scModal.hidden = !show;
    }
    if (scClose) scClose.addEventListener('click', function () { toggleShortcuts(false); });
    if (scModal) scModal.addEventListener('click', function (ev) {
        if (ev.target === scModal) toggleShortcuts(false);
    });

    /* ---------- Subscribe result toast (?sub=…) ---------- */
    var subState = new URLSearchParams(location.search).get('sub');
    if (subState && window.cbToast) {
        if (subState === '1') cbToast('📬 Subscribed! You\'ll hear about new posts.', 'ok');
        else if (subState === 'bad') cbToast('That email doesn\'t look right.', 'err');
        else cbToast('Could not subscribe — try again later.', 'err');
        history.replaceState(null, '', location.pathname); // clean the URL
    }

    /* ---------- Keyboard shortcuts ---------- */
    document.addEventListener('keydown', function (ev) {
        if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 'k') {
            ev.preventDefault();
            if (window.cbOpenSearch) cbOpenSearch();
        }
        var typing = /input|textarea|select/i.test(ev.target.tagName) || ev.target.isContentEditable;
        if (ev.key === '/' && !typing) {
            ev.preventDefault();
            if (window.cbOpenSearch) cbOpenSearch();
        }
        if (ev.key === '?' && !typing) {
            ev.preventDefault();
            toggleShortcuts(scModal && scModal.hidden);
        }
        if (ev.key === 'Escape') toggleShortcuts(false);
    });

    /* ---------- Konami code easter egg 🎮 ---------- */
    var konami = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
    var kPos = 0;
    document.addEventListener('keydown', function (ev) {
        kPos = (ev.key === konami[kPos]) ? kPos + 1 : (ev.key === konami[0] ? 1 : 0);
        if (kPos !== konami.length) return;
        kPos = 0;
        document.body.classList.add('konami');
        if (window.cbToast) cbToast('🎮 ACCESS GRANTED — hack the planet!', 'ok');
        setTimeout(function () { document.body.classList.remove('konami'); }, 6000);
    });

    /* ---------- Service worker (PWA) ---------- */
    if ('serviceWorker' in navigator && location.protocol === 'https:') {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () { /* non-fatal */ });
        });
    }

    /* ---------- Copy buttons on code blocks ---------- */
    document.querySelectorAll('.post-content pre').forEach(function (preEl) {
        var btn = document.createElement('button');
        btn.className = 'copy-btn';
        btn.type = 'button';
        btn.textContent = 'copy';
        btn.addEventListener('click', function () {
            var code = preEl.querySelector('code');
            cbCopy(code ? code.textContent : preEl.textContent, 'Code copied');
            btn.textContent = 'copied ✓';
            btn.classList.add('copied');
            setTimeout(function () { btn.textContent = 'copy'; btn.classList.remove('copied'); }, 1600);
        });
        preEl.appendChild(btn);
    });
})();
