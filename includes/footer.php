<?php require_once __DIR__ . '/functions.php'; ?>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <div class="footer-col">
            <div class="logo"><span class="logo-mark">🛡️</span> <span class="grad-text"><?= e(SITE_NAME) ?></span></div>
            <p class="muted"><?= e(SITE_TAGLINE) ?></p>
            <form method="post" action="/subscribe.php" class="subscribe-form">
                <?= csrfField() ?>
                <input type="text" name="website" class="hp-field" tabindex="-1" autocomplete="off" aria-hidden="true">
                <label for="subEmail" class="muted">📬 New post alerts</label>
                <div class="subscribe-row">
                    <input id="subEmail" name="email" type="email" required maxlength="255" placeholder="you@example.com">
                    <button class="btn btn-primary btn-sm">Subscribe</button>
                </div>
            </form>
        </div>
        <div class="footer-col">
            <h4>Explore</h4>
            <a href="/blog.php">Blog</a>
            <a href="/categories.php">Categories</a>
            <a href="/feed.php" class="rss-link">📡 RSS Feed</a>
            <a href="/sitemap.php">Sitemap</a>
        </div>
        <div class="footer-col">
            <h4>Site</h4>
            <a href="/about.php">About</a>
            <a href="/contact.php">Contact</a>
            <a href="/bookmarks.html">Bookmarks</a>
        </div>
    </div>
    <div class="container footer-bottom">
        <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?> · byte.pro.bd</span>
        <span class="muted">Built with security in mind 🔐 · press <kbd>?</kbd> for shortcuts</span>
    </div>
</footer>

<button id="backToTop" class="back-to-top" aria-label="Back to top" hidden>↑</button>
<div id="toastHost" class="toast-host" aria-live="polite"></div>
<div id="lightbox" class="lightbox" hidden><img alt=""><button class="lightbox-close" aria-label="Close">✕</button></div>

<div id="shortcutModal" class="shortcut-modal" hidden>
    <div class="shortcut-box glass">
        <h3>⌨️ Keyboard shortcuts</h3>
        <table class="shortcut-table">
            <tr><td><kbd>Ctrl</kbd>+<kbd>K</kbd> or <kbd>/</kbd></td><td>Search</td></tr>
            <tr><td><kbd>?</kbd></td><td>This help</td></tr>
            <tr><td><kbd>Esc</kbd></td><td>Close dialogs</td></tr>
            <tr><td><kbd>↑</kbd><kbd>↓</kbd> + <kbd>Enter</kbd></td><td>Navigate search results</td></tr>
        </table>
        <button class="btn btn-ghost btn-sm" id="shortcutClose">Close (Esc)</button>
    </div>
</div>

<script src="/assets/js/utils.js" defer></script>
<script src="/assets/js/app.js" defer></script>
<script src="/assets/js/search.js" defer></script>
<script src="/assets/js/effects.js" defer></script>
<?php if (!empty($pageScripts)) foreach ($pageScripts as $s): ?>
<script src="<?= e($s) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
