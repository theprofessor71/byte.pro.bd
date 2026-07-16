<?php
/**
 * Markdown renderer — escape-first design: ALL raw HTML is neutralized
 * before any markdown transform runs, so post content can never inject
 * script even if an admin account pastes something hostile.
 *
 * Supports: headings, bold/italic/strikethrough, inline code, fenced code
 * blocks (with language class for Prism), links, images, blockquotes,
 * ordered/unordered lists, hr, tables, paragraphs.
 */
declare(strict_types=1);

function renderMarkdown(string $md): string
{
    $md = str_replace(["\r\n", "\r"], "\n", $md);

    // 1) Pull fenced code blocks out first so nothing inside them is transformed
    $codeBlocks = [];
    $md = preg_replace_callback('/^```([a-zA-Z0-9+_-]*)\n(.*?)^```$/ms', function ($m) use (&$codeBlocks) {
        $lang = $m[1] !== '' ? strtolower($m[1]) : 'plaintext';
        $key  = "\x1A" . 'CB' . count($codeBlocks) . "\x1A";
        $codeBlocks[$key] = '<pre class="line-numbers"><code class="language-' . $lang . '">'
            . htmlspecialchars($m[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code></pre>';
        return "\n" . $key . "\n";
    }, $md);

    // 2) Neutralize every remaining < > & — raw HTML is now inert text
    $md = htmlspecialchars($md, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    // 3) Inline code
    $md = preg_replace_callback('/`([^`\n]+)`/', fn($m) => '<code class="inline-code">' . $m[1] . '</code>', $md);

    // 4) Images ![alt](src) — http(s) or site-relative only
    $md = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function ($m) {
        $src = $m[2];
        if (!preg_match('#^(https?://|/)#i', $src)) {
            return $m[0];
        }
        return '<img src="' . $src . '" alt="' . $m[1] . '" loading="lazy" class="post-img">';
    }, $md);

    // 5) Links [text](url) — http(s), site-relative, or mailto only (blocks javascript:)
    $md = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $url = $m[2];
        if (!preg_match('#^(https?://|/|mailto:)#i', $url)) {
            return $m[0];
        }
        $ext = preg_match('#^https?://#i', $url) && !str_starts_with($url, SITE_URL)
            ? ' target="_blank" rel="noopener noreferrer"' : '';
        return '<a href="' . $url . '"' . $ext . '>' . $m[1] . '</a>';
    }, $md);

    // 6) Bold / italic / strikethrough
    $md = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $md);
    $md = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $md);
    $md = preg_replace('/(?<![*\w])\*([^*\n]+)\*(?![*\w])/', '<em>$1</em>', $md);
    $md = preg_replace('/~~(.+?)~~/s', '<del>$1</del>', $md);

    // 7) Block-level pass, line by line
    $lines = explode("\n", $md);
    $html = '';
    $inUl = $inOl = $inQuote = false;
    $para = [];

    $flushPara = function () use (&$para, &$html) {
        if ($para) {
            $html .= '<p>' . implode('<br>', $para) . '</p>' . "\n";
            $para = [];
        }
    };
    $closeLists = function () use (&$inUl, &$inOl, &$inQuote, &$html) {
        if ($inUl)    { $html .= "</ul>\n";         $inUl = false; }
        if ($inOl)    { $html .= "</ol>\n";         $inOl = false; }
        if ($inQuote) { $html .= "</blockquote>\n"; $inQuote = false; }
    };

    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        $trim = trim($line);

        if (preg_match('/^\x1ACB\d+\x1A$/', $trim)) {           // code block placeholder
            $flushPara(); $closeLists();
            $html .= $trim . "\n";
        } elseif (preg_match('/^(#{1,6})\s+(.+)$/', $trim, $m)) { // heading (+ anchor id for TOC)
            $flushPara(); $closeLists();
            $lvl = strlen($m[1]);
            $id  = slugify(html_entity_decode(strip_tags($m[2])));
            $html .= "<h$lvl id=\"$id\">{$m[2]}</h$lvl>\n";
        } elseif (preg_match('/^(-{3,}|\*{3,})$/', $trim)) {      // hr
            $flushPara(); $closeLists();
            $html .= "<hr>\n";
        } elseif (preg_match('/^&gt;\s?(.*)$/', $trim, $m)) {     // blockquote (> was escaped)
            $flushPara();
            if (!$inQuote) { $html .= '<blockquote>'; $inQuote = true; }
            $html .= $m[1] . ' ';
        } elseif (preg_match('/^[-*+]\s+(.+)$/', $trim, $m)) {    // ul
            $flushPara();
            if ($inOl) { $html .= "</ol>\n"; $inOl = false; }
            if ($inQuote) { $html .= "</blockquote>\n"; $inQuote = false; }
            if (!$inUl) { $html .= "<ul>\n"; $inUl = true; }
            $html .= '<li>' . $m[1] . "</li>\n";
        } elseif (preg_match('/^\d+\.\s+(.+)$/', $trim, $m)) {    // ol
            $flushPara();
            if ($inUl) { $html .= "</ul>\n"; $inUl = false; }
            if ($inQuote) { $html .= "</blockquote>\n"; $inQuote = false; }
            if (!$inOl) { $html .= "<ol>\n"; $inOl = true; }
            $html .= '<li>' . $m[1] . "</li>\n";
        } elseif ($trim !== '' && str_contains($trim, '|')       // table
            && isset($lines[$i + 1]) && preg_match('/^\s*\|?[\s:|-]+\|?\s*$/', $lines[$i + 1])
            && str_contains($lines[$i + 1], '-')) {
            $flushPara(); $closeLists();
            $cells = fn($row) => array_map('trim', array_filter(explode('|', trim($row, '| ')), fn($c) => true));
            $html .= "<div class=\"table-wrap\"><table><thead><tr>";
            foreach ($cells($trim) as $c) $html .= "<th>$c</th>";
            $html .= "</tr></thead><tbody>";
            $i++; // skip separator
            while (isset($lines[$i + 1]) && str_contains($lines[$i + 1], '|') && trim($lines[$i + 1]) !== '') {
                $i++;
                $html .= '<tr>';
                foreach ($cells(trim($lines[$i])) as $c) $html .= "<td>$c</td>";
                $html .= '</tr>';
            }
            $html .= "</tbody></table></div>\n";
        } elseif ($trim === '') {
            $flushPara(); $closeLists();
        } else {
            if ($inQuote || $inUl || $inOl) $closeLists();
            $para[] = $trim;
        }
    }
    $flushPara(); $closeLists();

    // 8) Restore code blocks
    return strtr($html, $codeBlocks);
}

/** Extract h2/h3 headings for the table of contents. */
function extractToc(string $renderedHtml): array
{
    preg_match_all('/<h([23]) id="([^"]+)">(.*?)<\/h[23]>/s', $renderedHtml, $m, PREG_SET_ORDER);
    return array_map(fn($h) => [
        'level' => (int)$h[1],
        'id'    => $h[2],
        'text'  => trim(strip_tags($h[3])),
    ], $m);
}
