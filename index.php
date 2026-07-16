<?php
/**
 * Homepage — hero with particles + typing, stats counters, featured posts,
 * categories, tools marquee.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pdo = db();
$featured = $pdo->query("SELECT id, title, slug, excerpt, category, tags, thumbnail, views, reading_time, created_at
                         FROM posts WHERE status='published' ORDER BY created_at DESC LIMIT 6")->fetchAll();
$stats = $pdo->query("SELECT COUNT(*) posts, COALESCE(SUM(views),0) views FROM posts WHERE status='published'")->fetch();
$catCount = (int)$pdo->query("SELECT COUNT(DISTINCT category) c FROM posts WHERE status='published'")->fetch()['c'];
$commentCount = (int)$pdo->query("SELECT COUNT(*) c FROM comments WHERE status=1")->fetch()['c'];
$cats = $pdo->query("SELECT category, COUNT(*) c FROM posts WHERE status='published' GROUP BY category ORDER BY c DESC LIMIT 8")->fetchAll();

$pageTitle = 'Home';
$bodyClass = 'page-home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <canvas id="particles" aria-hidden="true"></canvas>
    <div class="container hero-inner">
        <p class="hero-kicker mono">$ whoami</p>
        <h1 class="hero-title">Hack. Learn. <span class="grad-text glitch" data-text="Defend.">Defend.</span></h1>
        <p class="hero-tagline mono">&gt; <span id="typingTarget" data-phrases='["CTF write-ups & walkthroughs","OWASP deep dives","Recon & tooling notes","Defense-first security research"]'></span><span class="cursor-blink">▌</span></p>
        <div class="hero-cta">
            <a class="btn btn-primary" href="/blog.php">Read the blog →</a>
            <a class="btn btn-ghost" href="/about.php">About me</a>
        </div>
    </div>
</section>

<section class="section stats-band">
    <div class="container stats-row">
        <div class="stat"><span class="stat-num counter" data-target="<?= (int)$stats['posts'] ?>">0</span><span class="stat-cap">Posts</span></div>
        <div class="stat"><span class="stat-num counter" data-target="<?= (int)$stats['views'] ?>">0</span><span class="stat-cap">Views</span></div>
        <div class="stat"><span class="stat-num counter" data-target="<?= $catCount ?>">0</span><span class="stat-cap">Categories</span></div>
        <div class="stat"><span class="stat-num counter" data-target="<?= $commentCount ?>">0</span><span class="stat-cap">Comments</span></div>
    </div>
</section>

<section class="section">
    <div class="container">
        <h2 class="section-title">📝 Latest posts</h2>
        <div class="post-grid">
            <?php foreach ($featured as $p) { include __DIR__ . '/includes/post-card.php'; } ?>
            <?php if (!$featured): ?><p class="muted">First post coming soon…</p><?php endif; ?>
        </div>
        <div class="center"><a class="btn btn-ghost" href="/blog.php">All posts →</a></div>
    </div>
</section>

<?php if ($cats): ?>
<section class="section">
    <div class="container">
        <h2 class="section-title">🗂 Categories</h2>
        <div class="cat-grid">
            <?php foreach ($cats as $c): ?>
            <a class="cat-card glass" href="/category/<?= e(rawurlencode($c['category'])) ?>" data-aos="fade-up">
                <span class="cat-name"><?= e($c['category']) ?></span>
                <span class="cat-count"><?= (int)$c['c'] ?> posts</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section tools-band" aria-label="Tools">
    <div class="marquee mono" aria-hidden="true">
        <div class="marquee-track">
            <?php $tools = ['nmap', 'burpsuite', 'metasploit', 'wireshark', 'ghidra', 'sqlmap', 'hashcat', 'gobuster', 'ffuf', 'nuclei'];
            foreach (array_merge($tools, $tools) as $t): ?>
            <span class="tool-chip">$ <?= e($t) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
