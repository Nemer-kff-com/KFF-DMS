<?php
/**
 * فحص التحمّل — لمدير النظام فقط (حدود آمنة، على نفس النظام فقط).
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/load_check.php';

$user = require_permission('admin.settings');
$limits = dms_load_check_limits();
$cooldown = dms_load_check_cooldown_remaining();

$pageTitle = __('load_check.title');
require __DIR__ . '/../includes/header.php';

$settingsHubActive = 'load_check';
require __DIR__ . '/../includes/settings_hub_nav.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('load_check.title')) ?></h1>
        <p class="page-sub mb-0"><?= e(__('load_check.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/settings.php')) ?>">
            <i class="bi bi-gear me-1"></i><?= e(__('nav.settings')) ?>
        </a>
    </div>
</div>

<div class="page-narrow">
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i><?= e(__('load_check.warning')) ?>
    </div>

    <div class="card-panel mb-3">
        <div class="card-header"><i class="bi bi-sliders me-1"></i><?= e(__('load_check.limits_title')) ?></div>
        <div class="card-body small">
            <ul class="mb-0">
                <li><?= e(str_replace([':n', ':c'], [(string) $limits['max_http_n'], (string) $limits['max_http_c']], __('load_check.limit_http'))) ?></li>
                <li><?= e(str_replace(':n', (string) $limits['max_db_workers'], __('load_check.limit_db'))) ?></li>
                <li><?= e(str_replace(':s', (string) $limits['cooldown_sec'], __('load_check.limit_cooldown'))) ?></li>
            </ul>
        </div>
    </div>

    <div class="card-panel mb-3">
        <div class="card-header"><i class="bi bi-play-circle me-1"></i><?= e(__('load_check.run_title')) ?></div>
        <div class="card-body">
            <form id="loadCheckForm" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="http" id="lc_http" value="1" checked>
                        <label class="form-check-label" for="lc_http"><?= e(__('load_check.opt_http')) ?></label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="db" id="lc_db" value="1" checked>
                        <label class="form-check-label" for="lc_db"><?= e(__('load_check.opt_db')) ?></label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="escalate" id="lc_esc" value="1" checked>
                        <label class="form-check-label" for="lc_esc"><?= e(__('load_check.opt_escalate')) ?></label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="lc_http_n"><?= e(__('load_check.http_n')) ?></label>
                    <input type="number" class="form-control" name="http_n" id="lc_http_n" min="1" max="<?= (int) $limits['max_http_n'] ?>" value="40">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="lc_http_c"><?= e(__('load_check.http_c')) ?></label>
                    <input type="number" class="form-control" name="http_c" id="lc_http_c" min="1" max="<?= (int) $limits['max_http_c'] ?>" value="5">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="lc_db_loops"><?= e(__('load_check.db_loops')) ?></label>
                    <input type="number" class="form-control" name="db_loops" id="lc_db_loops" min="1" max="100" value="30">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="confirm" id="lc_confirm" value="1" required>
                        <label class="form-check-label" for="lc_confirm"><?= e(__('load_check.confirm')) ?></label>
                    </div>
                </div>
                <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                    <button type="submit" class="btn btn-kff" id="lcRunBtn" <?= $cooldown > 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-activity me-1"></i><?= e(__('load_check.run')) ?>
                    </button>
                    <span class="text-muted small" id="lcStatus">
                        <?php if ($cooldown > 0): ?>
                            <?= e(str_replace(':s', (string) $cooldown, __('load_check.cooldown_left'))) ?>
                        <?php endif; ?>
                    </span>
                </div>
            </form>
        </div>
    </div>

    <div class="card-panel mb-3 d-none" id="lcResultCard">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clipboard-data me-1"></i><?= e(__('load_check.results')) ?></span>
            <span class="badge" id="lcPassBadge"></span>
        </div>
        <div class="card-body">
            <div class="row g-2 mb-3" id="lcStats"></div>
            <div id="lcChartWrap" class="d-none">
                <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-2 mb-2">
                    <strong><?= e(__('load_check.chart_title')) ?></strong>
                    <span class="text-muted small" id="lcChartHint"></span>
                </div>
                <div class="d-flex justify-content-between text-muted small mb-1 font-monospace" id="lcAxis" aria-hidden="true"></div>
                <div id="lcBars" class="d-flex flex-column gap-3"></div>
            </div>
            <details class="mt-3">
                <summary class="small text-muted"><?= e(__('load_check.raw_json')) ?></summary>
                <pre class="mb-0 mt-2 small" id="lcResultPre" dir="ltr" style="white-space:pre-wrap;max-height:18rem;overflow:auto"></pre>
            </details>
        </div>
    </div>
</div>
<style>
#lcBars .lc-track { height: 14px; background: var(--bs-secondary-bg, #e9ecef); border-radius: 4px; overflow: hidden; }
#lcBars .lc-fill { height: 100%; min-width: 2px; border-radius: 4px; }
#lcBars .lc-fill-http { background: #0d6efd; }
#lcBars .lc-fill-db { background: #198754; }
#lcBars .lc-head { display: flex; justify-content: space-between; gap: .75rem; font-size: .9rem; margin-bottom: .25rem; }
</style>
<script>
(function () {
    var form = document.getElementById('loadCheckForm');
    var btn = document.getElementById('lcRunBtn');
    var statusEl = document.getElementById('lcStatus');
    var card = document.getElementById('lcResultCard');
    var pre = document.getElementById('lcResultPre');
    var badge = document.getElementById('lcPassBadge');
    var statsEl = document.getElementById('lcStats');
    var chartWrap = document.getElementById('lcChartWrap');
    var barsEl = document.getElementById('lcBars');
    var axisEl = document.getElementById('lcAxis');
    var hintEl = document.getElementById('lcChartHint');
    if (!form || !btn) return;

    var L = {
        pass: <?= json_encode(__('load_check.pass'), JSON_UNESCAPED_UNICODE) ?>,
        fail: <?= json_encode(__('load_check.fail'), JSON_UNESCAPED_UNICODE) ?>,
        hint: <?= json_encode(__('load_check.chart_hint'), JSON_UNESCAPED_UNICODE) ?>,
        httpMean: <?= json_encode(__('load_check.bar_http_mean'), JSON_UNESCAPED_UNICODE) ?>,
        httpP95: <?= json_encode(__('load_check.bar_http_p95'), JSON_UNESCAPED_UNICODE) ?>,
        httpMax: <?= json_encode(__('load_check.bar_http_max'), JSON_UNESCAPED_UNICODE) ?>,
        dbMean: <?= json_encode(__('load_check.bar_db_mean'), JSON_UNESCAPED_UNICODE) ?>,
        dbP95: <?= json_encode(__('load_check.bar_db_p95'), JSON_UNESCAPED_UNICODE) ?>,
        dbMax: <?= json_encode(__('load_check.bar_db_max'), JSON_UNESCAPED_UNICODE) ?>,
        rps: <?= json_encode(__('load_check.stat_rps'), JSON_UNESCAPED_UNICODE) ?>,
        httpFail: <?= json_encode(__('load_check.stat_http_fail'), JSON_UNESCAPED_UNICODE) ?>,
        dbErr: <?= json_encode(__('load_check.stat_db_err'), JSON_UNESCAPED_UNICODE) ?>,
        escalate: <?= json_encode(__('load_check.stat_escalate'), JSON_UNESCAPED_UNICODE) ?>,
        escOk: <?= json_encode(__('load_check.escalate_ok'), JSON_UNESCAPED_UNICODE) ?>,
        escSkip: <?= json_encode(__('load_check.escalate_skip'), JSON_UNESCAPED_UNICODE) ?>,
        escBad: <?= json_encode(__('load_check.escalate_bad'), JSON_UNESCAPED_UNICODE) ?>
    };

    function pctile(samples, p) {
        if (!samples || !samples.length) return 0;
        var a = samples.slice().sort(function (x, y) { return x - y; });
        var idx = Math.min(a.length - 1, Math.max(0, Math.ceil(p * a.length) - 1));
        return a[idx];
    }
    function fmt(n) {
        return (Math.round(n * 100) / 100).toFixed(2).replace(/\.?0+$/, function (m) { return m === '.' ? '' : m; }) || '0';
    }
    function statCard(label, value, tone) {
        var toneClass = tone === 'ok' ? 'border-success' : (tone === 'bad' ? 'border-danger' : '');
        return '<div class="col-6 col-md-3"><div class="border rounded p-2 h-100 ' + toneClass + '">' +
            '<div class="text-muted small">' + label + '</div>' +
            '<div class="fw-semibold">' + value + '</div></div></div>';
    }
    function barRow(label, value, maxMs, kind) {
        var pct = maxMs > 0 ? Math.min(100, (value / maxMs) * 100) : 0;
        return '<div><div class="lc-head"><span>' + label + '</span><span class="text-muted font-monospace">' + fmt(value) + '</span></div>' +
            '<div class="lc-track" role="progressbar" aria-valuemin="0" aria-valuemax="' + maxMs + '" aria-valuenow="' + value + '" aria-label="' + label + '">' +
            '<div class="lc-fill lc-fill-' + kind + '" style="width:' + pct.toFixed(1) + '%"></div></div></div>';
    }
    function renderReport(j) {
        var report = (j && j.report) ? j.report : null;
        pre.textContent = JSON.stringify(j, null, 2);
        var pass = !!(j && j.success && report && report.pass);
        badge.className = 'badge ' + (pass ? 'text-bg-success' : 'text-bg-danger');
        badge.textContent = pass ? L.pass : L.fail;

        var html = '';
        var http = report && report.http && report.http.targets && report.http.targets[0] ? report.http.targets[0] : null;
        var db = report && report.db ? report.db : null;
        var esc = report && report.escalate ? report.escalate : null;

        if (http) {
            html += statCard(L.rps, fmt(http.rps || 0), 'ok');
            html += statCard(L.httpFail, (http.failed || 0) + ' / ' + (http.complete || 0), http.failed > 0 ? 'bad' : 'ok');
        }
        if (db) {
            html += statCard(L.dbErr, String(db.errors || 0), (db.errors || 0) > 0 ? 'bad' : 'ok');
        }
        if (esc) {
            var escLabel = esc.message === 'need_primary_and_alternate' ? L.escSkip : (esc.ok ? L.escOk : L.escBad);
            html += statCard(L.escalate, escLabel, esc.ok ? 'ok' : (esc.message === 'need_primary_and_alternate' ? '' : 'bad'));
        }
        statsEl.innerHTML = html;

        var rows = [];
        if (http) {
            var samples = http.samples || [];
            var mean = http.mean_ms != null ? http.mean_ms : 0;
            var p95 = samples.length ? pctile(samples, 0.95) : mean;
            var mx = samples.length ? Math.max.apply(null, samples) : mean;
            rows.push({ label: L.httpMean, value: mean, kind: 'http' });
            rows.push({ label: L.httpP95, value: p95, kind: 'http' });
            rows.push({ label: L.httpMax, value: mx, kind: 'http' });
        }
        if (db) {
            rows.push({ label: L.dbMean, value: db.avg_ms || 0, kind: 'db' });
            rows.push({ label: L.dbP95, value: db.p95_ms || 0, kind: 'db' });
            rows.push({ label: L.dbMax, value: db.max_ms || 0, kind: 'db' });
        }

        if (!rows.length) {
            chartWrap.classList.add('d-none');
            return;
        }
        var maxMs = 0;
        rows.forEach(function (r) { if (r.value > maxMs) maxMs = r.value; });
        maxMs = Math.max(5, Math.ceil(maxMs / 5) * 5);
        var ticks = [];
        for (var t = 0; t <= maxMs; t += Math.max(1, maxMs / 7)) {
            ticks.push(String(Math.round(t)));
        }
        if (ticks[ticks.length - 1] !== String(maxMs)) ticks.push(String(maxMs));
        axisEl.innerHTML = ticks.map(function (x) { return '<span>' + x + '</span>'; }).join('');
        hintEl.textContent = L.hint + (http ? (' · HTTP ' + fmt(http.mean_ms || 0)) : '') + (db ? (' · DB ' + fmt(db.avg_ms || 0)) : '');
        barsEl.innerHTML = rows.map(function (r) { return barRow(r.label, r.value, maxMs, r.kind); }).join('');
        chartWrap.classList.remove('d-none');
    }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (!document.getElementById('lc_confirm').checked) {
            statusEl.textContent = <?= json_encode(__('load_check.confirm_required'), JSON_UNESCAPED_UNICODE) ?>;
            return;
        }
        btn.disabled = true;
        statusEl.textContent = <?= json_encode(__('load_check.running'), JSON_UNESCAPED_UNICODE) ?>;
        var fd = new FormData(form);
        fetch(<?= json_encode(base_url('admin/load_check_run.php'), JSON_UNESCAPED_UNICODE) ?>, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
            card.classList.remove('d-none');
            renderReport(res.j || {});
            statusEl.textContent = (res.j && res.j.message) ? res.j.message : '';
            var wait = (res.j && res.j.cooldown_left) ? parseInt(res.j.cooldown_left, 10) : 0;
            if (wait > 0 || (res.j && res.j.cooldown)) {
                btn.disabled = true;
                if (wait > 0) {
                    setTimeout(function () { btn.disabled = false; statusEl.textContent = ''; }, wait * 1000);
                }
            } else {
                btn.disabled = false;
            }
        }).catch(function () {
            statusEl.textContent = <?= json_encode(__('load_check.request_failed'), JSON_UNESCAPED_UNICODE) ?>;
            btn.disabled = false;
        });
    });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
