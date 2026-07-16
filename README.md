# 🛡️ CyberBlogs — Deployment Guide (byte.pro.bd)

Custom PHP 8 + MySQL CMS, OWASP Top 10 hardened. No frameworks, no third-party
services, CSP locked to `'self'`.

## 1. Upload

Upload everything to `htdocs/` on InfinityFree. Notes:
- `sql/` must be uploaded too — `install.php` reads `sql/schema.sql` from disk
  (web access to the folder is denied by `sql/.htaccess`).
- Read `assets/vendor/README.md` first: download `prism.js` and
  `chart.min.js` into `assets/vendor/` (optional but recommended)

Create two PNG icons in `assets/icons/`: `icon-192.png`, `icon-512.png`
(export from `favicon.svg`).

## 2. Database

InfinityFree control panel → MySQL Databases → create one. Note the
host / db name / username / password.

## 3. Install

Visit `https://byte.pro.bd/install.php`, fill in DB + first admin
(password min 12 chars). The installer:
- creates all 11 tables
- writes `config/config.php` with a random 64-hex secret key
- creates the admin with BCRYPT (cost 12) password + **encrypted** TOTP secret
- shows the 2FA setup key **once** — add it to Google Authenticator NOW
  ("Enter a setup key" → paste the base32 secret, Time based). No QR image is
  shown on purpose: rendering one via a third-party API would leak the secret.
- self-locks via `config/install.lock`

Then **delete `install.php` from the server.** Non-negotiable.

## 4. Cloudflare

1. Add byte.pro.bd to Cloudflare (free plan), switch nameservers.
2. SSL/TLS → **Full** (InfinityFree free hosting has no custom origin cert
   management, so Full Strict will error — Full still encrypts both legs).
3. Enable: Always Use HTTPS, Bot Fight Mode, Browser Integrity Check.
4. Cache rule: bypass cache for `/admin/*` (the .htaccess no-store headers
   already tell it to, this is belt-and-braces).

The app reads visitor IPs from `CF-Connecting-IP` **only when the request
actually comes from Cloudflare's published ranges** (`config/security.php`),
so brute-force lockouts hit the real attacker, not the proxy. If Cloudflare
adds new ranges later, update `ipFromCloudflare()` from cloudflare.com/ips.

## 5. Known InfinityFree quirks

- Their browser-check (`?i=1` redirect) can break `feed.php` / `sitemap.php`
  for bots. Test with `curl -A "Googlebot"` after DNS moves to Cloudflare —
  proxied traffic usually skips the check.
- PHP `mail()` is unreliable there — that's why contact messages go to the DB.
- Cron is unavailable — nothing here needs it.

## 6. Post-launch security checklist

- [ ] `install.php` deleted from server
- [ ] `https://byte.pro.bd/config/config.php` returns 403/404 (not code!)
- [ ] `https://byte.pro.bd/protected_uploads/` returns 403
- [ ] Login lockout works: 3 wrong passwords → blocked 15 min
- [ ] 2FA required after correct password
- [ ] Comment with `<script>alert(1)</script>` renders as text
- [ ] `download.php?file=../../etc/passwd` → 404
- [ ] Security headers present (securityheaders.com scan)
- [ ] Lighthouse ≥ 90

## Layout

```
config/     DB + security core (web access denied)
includes/   helpers, auth, TOTP, markdown (web access denied)
admin/      2FA-protected panel (login, dashboard, posts, comments,
            messages, files, subscribers, analytics, logs, backup, settings)
api/        react.php + comment-vote.php (CSRF-protected endpoints)
assets/     css / js / icons / vendor
sql/        schema.sql (installer), sample-posts.sql (optional),
            upgrade-v2.sql (ONLY for upgrading a pre-v2 database)
*.php       public pages
```

## Feature pack v2

Included on top of the original build:

- **Post series** — multi-part write-ups with a "Part 1/2/3" nav box
- **Difficulty badges** (easy/medium/hard/insane) for CTF posts
- **Comment upvotes** (▲, ip-hash dedup, best comments float up)
- **Draft preview links** — share unpublished posts via secret token
- **Revision history** — last 10 versions per post, one-click restore
- **Post duplicate** (⧉ in Posts list) — copy as new draft
- **Author pages** (`/author.php?id=N`) + byline on posts
- **Newsletter signup** (footer) → admin Subscribers + CSV export
- **Analytics** — 30-day views, top posts, reactions, attack summary
- **DB backup** — one-click full SQL dump download
- **New-IP login alert** on the dashboard
- **Search category filter**, smarter related posts (tag overlap)
- **404 "did you mean"** slug suggestions
- **Accent color picker** (🎨 in header), shortcut help (`?`),
  interactive terminal on About, Konami code easter egg 🎮

**Upgrading an existing pre-v2 database?** Run `sql/upgrade-v2.sql` in
phpMyAdmin once. Fresh installs need nothing — schema.sql has it all.

## Admin quick reference

- Login: `/admin/login.php` (password → TOTP)
- Session: 30 min idle timeout, UA-bound, regenerated on privilege change
- All state-changing actions: POST + CSRF (`hash_equals`)
- Everything logged to `security_logs` (viewer: `/admin/logs.php`)
