<?php
/**
 * Public page header. Set $pageTitle / $pageDesc / $bodyClass before including.
 */
require_once __DIR__ . '/functions.php';
sendSecurityHeaders();
$pageTitle = $pageTitle ?? SITE_NAME;
$pageDesc  = $pageDesc ?? SITE_DESC;
$canonical = SITE_URL . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:type" content="<?= isset($ogType) ? e($ogType) : 'website' ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/assets/icons/favicon.svg" type="image/svg+xml">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#0a0e17">
<link rel="alternate" type="application/rss+xml" title="<?= e(SITE_NAME) ?> RSS" href="/feed.php">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/animations.css">
<link rel="stylesheet" href="/assets/css/prism-theme.css">
<script src="/assets/js/theme-init.js"></script>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<div id="preloader" aria-hidden="true"><div class="preloader-inner"><span class="preloader-glyph">&gt;_</span><span class="preloader-text">byte.pro.bd</span></div></div>
<div id="progressBar" aria-hidden="true"></div>

<header class="site-header glass">
    <div class="container header-inner">
        <a class="logo" href="/">
            <span class="logo-mark">🛡️</span>
            <span class="logo-text grad-text"><?= e(SITE_NAME) ?></span>
        </a>
        <nav class="main-nav" id="mainNav">
            <a href="/">Home</a>
            <a href="/blog.php">Blog</a>
            <a href="/categories.php">Categories</a>
            <a href="/about.php">About</a>
            <a href="/contact.php">Contact</a>
            <a href="/bookmarks.html">Bookmarks</a>
        </nav>
        <div class="header-actions">
            <button id="searchBtn" class="icon-btn" title="Search (Ctrl+K)" aria-label="Search">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            </button>
            <button id="accentBtn" class="icon-btn" title="Accent color" aria-label="Accent color">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
            </button>
            <div id="accentMenu" class="accent-menu glass" hidden>
                <button data-accent="" title="Default (cyan)"><span style="background:#00f0ff"></span></button>
                <button data-accent="green" title="Green"><span style="background:#00ff88"></span></button>
                <button data-accent="purple" title="Purple"><span style="background:#a855f7"></span></button>
                <button data-accent="red" title="Red"><span style="background:#ff4d5e"></span></button>
                <button data-accent="amber" title="Amber"><span style="background:#f59e0b"></span></button>
            </div>
            <button id="themeToggle" class="icon-btn" title="Toggle theme" aria-label="Toggle theme">
                <svg class="icon-moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
                <svg class="icon-sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4"/></svg>
            </button>
            <button id="navToggle" class="icon-btn nav-toggle" aria-label="Menu">☰</button>
        </div>
    </div>
</header>

<div id="searchModal" class="search-modal" hidden>
    <div class="search-box glass">
        <input id="searchInput" placeholder="Search posts…  (Esc to close)" autocomplete="off">
        <div id="searchResults" class="search-results"></div>
    </div>
</div>

<main class="site-main">
