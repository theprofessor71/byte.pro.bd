/* admin-dashboard.js — Chart.js charts (views trend + category doughnut).
   Falls back to a plain-text summary if Chart.js failed to load. */
(function () {
    'use strict';

    function dataOf(el, key) {
        try { return JSON.parse(el.dataset[key] || '[]'); } catch (e) { return []; }
    }

    var views = document.getElementById('viewsChart');
    var cats = document.getElementById('catChart');
    var hasChart = typeof window.Chart === 'function';

    if (views) {
        var vLabels = dataOf(views, 'labels');
        var vData = dataOf(views, 'values');
        if (hasChart) {
            new Chart(views, {
                type: 'line',
                data: {
                    labels: vLabels,
                    datasets: [{
                        label: 'Views',
                        data: vData,
                        borderColor: '#00f0ff',
                        backgroundColor: 'rgba(0, 240, 255, 0.10)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 2,
                        borderWidth: 2
                    }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#94a3b8', maxTicksLimit: 10 }, grid: { color: 'rgba(148,163,184,0.08)' } },
                        y: { beginAtZero: true, ticks: { color: '#94a3b8', precision: 0 }, grid: { color: 'rgba(148,163,184,0.08)' } }
                    }
                }
            });
        } else {
            fallback(views, 'Total last 30 days: ' + vData.reduce(function (a, b) { return a + b; }, 0) + ' views');
        }
    }

    if (cats) {
        var cLabels = dataOf(cats, 'labels');
        var cData = dataOf(cats, 'values');
        if (hasChart) {
            new Chart(cats, {
                type: 'doughnut',
                data: {
                    labels: cLabels,
                    datasets: [{
                        data: cData,
                        backgroundColor: ['#00f0ff', '#00ff88', '#c792ea', '#ffb86c', '#ff7a93', '#7fd6e8', '#ffd76d', '#94a3b8'],
                        borderColor: '#0a0e17',
                        borderWidth: 2
                    }]
                },
                options: {
                    plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 12 } } },
                    cutout: '62%'
                }
            });
        } else {
            fallback(cats, cLabels.map(function (l, i) { return l + ': ' + cData[i]; }).join(' · '));
        }
    }

    function fallback(canvas, text) {
        var p = document.createElement('p');
        p.className = 'muted';
        p.textContent = text + ' (chart library not loaded)';
        canvas.replaceWith(p);
    }
})();
