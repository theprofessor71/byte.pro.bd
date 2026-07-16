-- Optional sample posts — run AFTER install.php (needs admin id 1 to exist).
-- Gives the homepage/blog something to show on day one. Delete/edit freely.

INSERT INTO posts (title, slug, content, excerpt, category, tags, status, reading_time, author_id) VALUES
(
    'Welcome to CyberBlogs',
    'welcome-to-cyberblogs',
    '## Hello, world\n\nThis is **CyberBlogs** — a hand-rolled, security-hardened PHP CMS running on byte.pro.bd.\n\n### What to expect here\n\n- CTF write-ups and walkthroughs\n- OWASP Top 10 deep dives\n- Recon and tooling notes\n- Defensive hardening guides\n\n> Everything on this site is for **education and authorized testing only**.\n\n```bash\nwhoami\n# security enthusiast, Bangladesh\n```\n\nStick around — use `Ctrl+K` to search, bookmark posts with the 🔖 button, and say hi via the [contact page](/contact.php).',
    'First post — what CyberBlogs is about and what to expect: CTF write-ups, OWASP deep dives, recon notes and defensive guides.',
    'General',
    'welcome, meta',
    'published',
    1,
    1
),
(
    'OWASP Top 10 in Plain Words',
    'owasp-top-10-plain-words',
    '## Why this list matters\n\nThe OWASP Top 10 is the most-cited map of web application risk. This post walks each entry with a one-line "what it really means".\n\n## The quick version\n\n| # | Risk | Plain words |\n|---|------|-------------|\n| A01 | Broken Access Control | users can reach things they should not |\n| A02 | Cryptographic Failures | secrets stored or sent carelessly |\n| A03 | Injection | user input treated as code |\n\n## SQL injection in one example\n\n```php\n// vulnerable\n$db->query("SELECT * FROM users WHERE name = \'" . $_GET[\'name\'] . "\'");\n\n// safe — prepared statement\n$stmt = $db->prepare(\'SELECT * FROM users WHERE name = ?\');\n$stmt->execute([$_GET[\'name\'] ?? \'\']);\n```\n\nThe fix is always the same idea: **never let data become code.**\n\nMore deep dives coming — one post per category.',
    'The OWASP Top 10 explained in plain words, with a hands-on SQL injection example and the prepared-statement fix.',
    'Web Security',
    'owasp, sqli, basics',
    'published',
    3,
    1
);
