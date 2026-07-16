/* effects.js — particles canvas, typing animation, matrix rain, counters.
   All hand-rolled: no external libs, everything degrades gracefully. */
(function () {
    'use strict';
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- Particles (hero) ---------- */
    var canvas = document.getElementById('particles');
    if (canvas && !reduced) {
        var ctx = canvas.getContext('2d');
        var dots = [];
        var W, H;
        function resize() {
            W = canvas.width = canvas.offsetWidth;
            H = canvas.height = canvas.offsetHeight;
        }
        resize();
        window.addEventListener('resize', resize);
        var COUNT = Math.min(70, Math.floor(W / 18));
        for (var i = 0; i < COUNT; i++) {
            dots.push({
                x: Math.random() * W, y: Math.random() * H,
                vx: (Math.random() - 0.5) * 0.4, vy: (Math.random() - 0.5) * 0.4,
                r: Math.random() * 1.6 + 0.6
            });
        }
        (function tick() {
            ctx.clearRect(0, 0, W, H);
            var accent = getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#00f0ff';
            for (var i = 0; i < dots.length; i++) {
                var d = dots[i];
                d.x += d.vx; d.y += d.vy;
                if (d.x < 0 || d.x > W) d.vx *= -1;
                if (d.y < 0 || d.y > H) d.vy *= -1;
                ctx.beginPath();
                ctx.arc(d.x, d.y, d.r, 0, Math.PI * 2);
                ctx.fillStyle = accent + '55';
                ctx.fill();
                for (var j = i + 1; j < dots.length; j++) {
                    var o = dots[j];
                    var dx = d.x - o.x, dy = d.y - o.y;
                    var dist = dx * dx + dy * dy;
                    if (dist < 110 * 110) {
                        ctx.beginPath();
                        ctx.moveTo(d.x, d.y);
                        ctx.lineTo(o.x, o.y);
                        ctx.strokeStyle = accent + '18';
                        ctx.lineWidth = 1;
                        ctx.stroke();
                    }
                }
            }
            requestAnimationFrame(tick);
        })();
    }

    /* ---------- Typing animation ---------- */
    var typer = document.getElementById('typingTarget');
    if (typer) {
        var phrases = [];
        try { phrases = JSON.parse(typer.dataset.phrases || '[]'); } catch (e) {}
        if (!phrases.length) phrases = ['security research & write-ups'];
        if (reduced) {
            typer.textContent = phrases[0];
        } else {
            var pi = 0, ci = 0, deleting = false;
            (function type() {
                var phrase = phrases[pi];
                typer.textContent = phrase.slice(0, ci);
                if (!deleting && ci < phrase.length) { ci++; setTimeout(type, 55); }
                else if (!deleting) { deleting = true; setTimeout(type, 2100); }
                else if (ci > 0) { ci--; setTimeout(type, 28); }
                else { deleting = false; pi = (pi + 1) % phrases.length; setTimeout(type, 350); }
            })();
        }
    }

    /* ---------- Matrix rain (404) ---------- */
    var rain = document.getElementById('matrixRain');
    if (rain && !reduced) {
        var rctx = rain.getContext('2d');
        function rsize() {
            rain.width = rain.offsetWidth;
            rain.height = rain.offsetHeight;
        }
        rsize();
        window.addEventListener('resize', rsize);
        var chars = 'アカサタナハマヤラワ0123456789ABCDEF<>/\\{}[]$#@!?';
        var fontSize = 15;
        var drops = [];
        setInterval(function () {
            rctx.fillStyle = 'rgba(10, 14, 23, 0.12)';
            rctx.fillRect(0, 0, rain.width, rain.height);
            rctx.fillStyle = '#00ff88';
            rctx.font = fontSize + 'px monospace';
            var cols = Math.floor(rain.width / fontSize);
            for (var i = 0; i < cols; i++) {
                if (drops[i] === undefined) drops[i] = Math.random() * -40;
                var ch = chars[Math.floor(Math.random() * chars.length)];
                rctx.fillText(ch, i * fontSize, drops[i] * fontSize);
                if (drops[i] * fontSize > rain.height && Math.random() > 0.975) drops[i] = 0;
                drops[i]++;
            }
        }, 50);
    }

    /* ---------- Animated counters ---------- */
    var counters = document.querySelectorAll('.counter');
    if (counters.length && 'IntersectionObserver' in window && !reduced) {
        var cio = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (!en.isIntersecting) return;
                cio.unobserve(en.target);
                var el = en.target;
                var target = parseInt(el.dataset.target || '0', 10);
                var start = null;
                var dur = 1400;
                (function step(ts) {
                    if (!start) start = ts;
                    var p = Math.min((ts - start) / dur, 1);
                    el.textContent = Math.floor(target * (1 - Math.pow(1 - p, 3))).toLocaleString();
                    if (p < 1) requestAnimationFrame(step);
                })(performance.now());
            });
        }, { threshold: 0.4 });
        counters.forEach(function (el) { cio.observe(el); });
    } else {
        counters.forEach(function (el) {
            el.textContent = parseInt(el.dataset.target || '0', 10).toLocaleString();
        });
    }
})();
