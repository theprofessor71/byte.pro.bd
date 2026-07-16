<?php
/**
 * About page.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'About';
$pageScripts = ['/assets/js/terminal.js'];
require __DIR__ . '/includes/header.php';

$skills = [
    'Web App Security' => 90, 'Network Recon' => 85, 'CTF / Wargames' => 88,
    'Linux & Scripting' => 92, 'OSINT' => 75, 'Reverse Engineering' => 60,
];
$tools = ['Nmap', 'Burp Suite', 'Metasploit', 'Wireshark', 'sqlmap', 'ffuf', 'Ghidra', 'hashcat'];
?>
<div class="container page-head">
    <h1 class="page-heading grad-text">About</h1>
</div>
<div class="container about-grid">
    <div class="glass panel-lg" data-aos="fade-up">
        <p class="mono muted">$ cat about.txt</p>
        <h2>Hey, I run <?= e(SITE_NAME) ?> 👋</h2>
        <p>Security enthusiast from Bangladesh 🇧🇩 writing about ethical hacking, CTF write-ups,
           defensive hardening and the tooling behind it all. Everything here is for education
           and authorized testing only.</p>
        <p>This site itself is a lab experiment: a hand-rolled PHP CMS hardened against the
           OWASP Top 10 — no frameworks, no trackers, no nonsense.</p>
    </div>

    <div class="glass panel-lg" data-aos="fade-up">
        <h3>⚡ Skills</h3>
        <?php foreach ($skills as $name => $pct): ?>
        <div class="skill-row">
            <span class="skill-name"><?= e($name) ?></span>
            <div class="skill-bar"><div class="skill-fill" style="--pct: <?= (int)$pct ?>%"></div></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="glass panel-lg" data-aos="fade-up">
        <h3>🧰 Toolbox</h3>
        <div class="tag-cloud">
            <?php foreach ($tools as $t): ?><span class="chip"><?= e($t) ?></span><?php endforeach; ?>
        </div>
        <h3 style="margin-top:24px">📫 Reach me</h3>
        <p>Use the <a href="/contact.php">contact form</a> — messages land straight in my dashboard, no third parties involved.</p>
    </div>

    <div class="glass panel-lg terminal-panel" data-aos="fade-up">
        <h3>💻 Try the terminal</h3>
        <div class="fake-terminal" id="fakeTerminal">
            <div class="term-bar"><span class="term-dot red"></span><span class="term-dot yellow"></span><span class="term-dot green"></span><span class="term-title mono">guest@byte.pro.bd:~</span></div>
            <div class="term-body mono" id="termBody">
                <div class="term-line muted">Type <code>help</code> and press Enter.</div>
            </div>
            <div class="term-input-row mono">
                <span class="term-prompt">guest@byte:~$</span>
                <input id="termInput" class="term-input" autocomplete="off" spellcheck="false" aria-label="Terminal input">
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
