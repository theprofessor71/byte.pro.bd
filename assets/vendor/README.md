# Vendor libraries — download before going live

The site works without these (graceful fallbacks are built in:
code blocks render unstyled, dashboard charts show text summaries),
but download them for the full experience. All are free/MIT.

| File to save here          | Get it from |
|----------------------------|-------------|
| `prism.js`                 | https://prismjs.com/download.html — Core + languages you write about (bash, php, python, javascript, sql, http) + **Line Numbers** plugin, MINIFIED |
| `chart.min.js`             | https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js (rename to chart.min.js) |

Why self-hosted instead of CDN?
The Content-Security-Policy is `script-src 'self'` — no third-party script
origins allowed. Self-hosting keeps that strict policy intact.

Note: the site does NOT use particles.js or AOS — those effects are
hand-rolled in `/assets/js/effects.js` and `app.js` (lighter + CSP-clean).
Prism's CSS is also already replaced by `/assets/css/prism-theme.css`.
