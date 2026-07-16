/* comments.js — reply threading UI, reactions API, bookmark & copy-link buttons */
(function () {
    'use strict';

    var article = document.querySelector('[data-post-id]');
    if (!article) return;
    var postId = article.dataset.postId;
    var csrf = (document.querySelector('input[name="csrf_token"]') || {}).value || '';

    /* ---------- Reply threading ---------- */
    var parentInput = document.getElementById('parentId');
    var banner = document.getElementById('replyBanner');
    var replyName = document.getElementById('replyName');
    var cancelBtn = document.getElementById('cancelReply');
    var form = document.getElementById('commentForm');

    document.querySelectorAll('.reply-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!parentInput || !banner) return;
            parentInput.value = btn.dataset.id;
            replyName.textContent = btn.dataset.name;
            banner.hidden = false;
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            form.querySelector('textarea').focus();
        });
    });
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
            parentInput.value = '';
            banner.hidden = true;
        });
    }

    /* ---------- Reactions ---------- */
    var reactedKey = 'cb-reacted-' + postId;
    var reacted = [];
    try { reacted = JSON.parse(localStorage.getItem(reactedKey) || '[]'); } catch (e) {}

    document.querySelectorAll('.reaction-btn').forEach(function (btn) {
        if (reacted.indexOf(btn.dataset.reaction) >= 0) btn.classList.add('reacted');

        btn.addEventListener('click', function () {
            var body = new URLSearchParams();
            body.set('post_id', postId);
            body.set('reaction', btn.dataset.reaction);
            body.set('csrf_token', csrf);

            btn.disabled = true;
            fetch('/api/react.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) { cbToast(data.error || 'Failed', 'err'); return; }
                    document.querySelectorAll('.reaction-btn').forEach(function (b) {
                        var c = b.querySelector('.reaction-count');
                        if (c) c.textContent = (data.counts && data.counts[b.dataset.reaction]) || 0;
                    });
                    var on = data.toggled === 'on';
                    btn.classList.toggle('reacted', on);
                    btn.classList.add('pop');
                    setTimeout(function () { btn.classList.remove('pop'); }, 380);
                    var idx = reacted.indexOf(btn.dataset.reaction);
                    if (on && idx < 0) reacted.push(btn.dataset.reaction);
                    if (!on && idx >= 0) reacted.splice(idx, 1);
                    localStorage.setItem(reactedKey, JSON.stringify(reacted));
                })
                .catch(function () { cbToast('Network error', 'err'); })
                .finally(function () { btn.disabled = false; });
        });
    });

    /* ---------- Bookmark button ---------- */
    var bm = document.getElementById('bookmarkBtn');
    if (bm && window.cbBookmarks) {
        function paint() {
            bm.style.color = cbBookmarks.has(bm.dataset.slug) ? 'var(--accent-2)' : '';
        }
        paint();
        bm.addEventListener('click', function () {
            var added = cbBookmarks.toggle(bm.dataset.slug, bm.dataset.title);
            cbToast(added ? '🔖 Bookmarked' : 'Bookmark removed', 'ok');
            paint();
        });
    }

    /* ---------- Copy link ---------- */
    var copyBtn = document.getElementById('copyLinkBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function () {
            cbCopy(copyBtn.dataset.url, 'Link copied');
        });
    }

    /* ---------- Print (CSP-safe, no inline onclick) ---------- */
    var printBtn = document.getElementById('printBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function () { window.print(); });
    }

    /* ---------- Comment upvotes ---------- */
    var votedKey = 'cb-cvoted-' + postId;
    var voted = [];
    try { voted = JSON.parse(localStorage.getItem(votedKey) || '[]'); } catch (e) {}

    document.querySelectorAll('.upvote-btn').forEach(function (btn) {
        if (voted.indexOf(btn.dataset.cid) >= 0) btn.classList.add('voted');

        btn.addEventListener('click', function () {
            var body = new URLSearchParams();
            body.set('comment_id', btn.dataset.cid);
            body.set('csrf_token', csrf);

            btn.disabled = true;
            fetch('/api/comment-vote.php', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.ok) { cbToast(data.error || 'Failed', 'err'); return; }
                    var c = btn.querySelector('.upvote-count');
                    if (c) c.textContent = data.votes;
                    var on = data.toggled === 'on';
                    btn.classList.toggle('voted', on);
                    var idx = voted.indexOf(btn.dataset.cid);
                    if (on && idx < 0) voted.push(btn.dataset.cid);
                    if (!on && idx >= 0) voted.splice(idx, 1);
                    localStorage.setItem(votedKey, JSON.stringify(voted));
                })
                .catch(function () { cbToast('Network error', 'err'); })
                .finally(function () { btn.disabled = false; });
        });
    });
})();
