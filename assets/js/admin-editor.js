/* admin-editor.js — markdown toolbar + lightweight client-side preview.
   The preview is an approximation; the server's renderMarkdown() is canonical. */
(function () {
    'use strict';

    var ta = document.getElementById('mdContent');
    var preview = document.getElementById('mdPreview');
    var toolbar = document.getElementById('mdToolbar');
    var toggleBtn = document.getElementById('togglePreview');
    if (!ta || !toolbar) return;

    /* ---------- Toolbar insertions ---------- */
    var actions = {
        bold:   { pre: '**', post: '**', ph: 'bold text' },
        italic: { pre: '*', post: '*', ph: 'italic text' },
        h2:     { pre: '\n## ', post: '\n', ph: 'Heading' },
        link:   { pre: '[', post: '](https://)', ph: 'link text' },
        image:  { pre: '![', post: '](/assets/images/… or https://…)', ph: 'alt text' },
        code:   { pre: '\n```bash\n', post: '\n```\n', ph: 'command here' },
        quote:  { pre: '\n> ', post: '\n', ph: 'quote' },
        list:   { pre: '\n- ', post: '\n', ph: 'item' }
    };
    toolbar.addEventListener('click', function (ev) {
        var btn = ev.target.closest('button[data-md]');
        if (!btn) return;
        var a = actions[btn.dataset.md];
        if (!a) return;
        var s = ta.selectionStart, e = ta.selectionEnd;
        var sel = ta.value.slice(s, e) || a.ph;
        ta.value = ta.value.slice(0, s) + a.pre + sel + a.post + ta.value.slice(e);
        ta.focus();
        ta.selectionStart = s + a.pre.length;
        ta.selectionEnd = s + a.pre.length + sel.length;
        if (preview && !preview.hidden) render();
    });

    /* ---------- Preview toggle ---------- */
    if (toggleBtn && preview) {
        toggleBtn.addEventListener('click', function () {
            var show = preview.hidden;
            preview.hidden = !show;
            ta.style.display = show ? 'none' : '';
            toggleBtn.textContent = show ? '✏️ Edit' : '👁 Preview';
            if (show) render();
        });
        ta.addEventListener('input', function () {
            if (!preview.hidden) render();
        });
    }

    /* ---------- Minimal client-side markdown (escape-first, mirrors server) ---------- */
    function esc(s) {
        return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function render() {
        var src = ta.value;
        var blocks = [];
        src = src.replace(/^```([a-zA-Z0-9+_-]*)\n([\s\S]*?)^```$/gm, function (_, lang, code) {
            blocks.push('<pre><code>' + esc(code) + '</code></pre>');
            return 'B' + (blocks.length - 1) + '';
        });
        var out = esc(src)
            .replace(/`([^`\n]+)`/g, '<code class="inline-code">$1</code>')
            .replace(/^###### (.*)$/gm, '<h6>$1</h6>')
            .replace(/^##### (.*)$/gm, '<h5>$1</h5>')
            .replace(/^#### (.*)$/gm, '<h4>$1</h4>')
            .replace(/^### (.*)$/gm, '<h3>$1</h3>')
            .replace(/^## (.*)$/gm, '<h2>$1</h2>')
            .replace(/^# (.*)$/gm, '<h1>$1</h1>')
            .replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/~~(.+?)~~/g, '<del>$1</del>')
            .replace(/!\[([^\]]*)\]\((https?:\/\/[^)\s]+|\/[^)\s]*)\)/g, '<img src="$2" alt="$1" style="max-width:100%">')
            .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+|\/[^)\s]*)\)/g, '<a href="$2">$1</a>')
            .replace(/^&gt; ?(.*)$/gm, '<blockquote>$1</blockquote>')
            .replace(/^[-*+] (.*)$/gm, '<li>$1</li>')
            .replace(/^\d+\. (.*)$/gm, '<li>$1</li>')
            .replace(/^(-{3,}|\*{3,})$/gm, '<hr>')
            .replace(/\n{2,}/g, '</p><p>');
        out = '<p>' + out + '</p>';
        out = out.replace(/B(\d+)/g, function (_, i) { return blocks[+i] || ''; });
        preview.innerHTML = out;
    }

    /* ---------- Ctrl+S saves ---------- */
    document.addEventListener('keydown', function (ev) {
        if ((ev.ctrlKey || ev.metaKey) && ev.key.toLowerCase() === 's') {
            ev.preventDefault();
            var f = ta.closest('form');
            if (f) f.requestSubmit();
        }
    });

    /* ---------- Unsaved-changes guard ---------- */
    var dirty = false;
    ta.addEventListener('input', function () { dirty = true; });
    var form = ta.closest('form');
    if (form) form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (ev) {
        if (dirty) { ev.preventDefault(); ev.returnValue = ''; }
    });
})();
