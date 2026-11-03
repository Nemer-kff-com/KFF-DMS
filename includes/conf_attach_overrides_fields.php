<?php
/**
 * تجاوز مرفقات السرية — زر بجانب السرية + لوحة تفتح فوقه مباشرة.
 */
if (empty($GLOBALS['_dms_conf_override_state'])) {
    $confOverrideSource = is_array($corr ?? null) ? $corr : (is_array($item ?? null) ? $item : []);
    $confOverrideCurrent = function_exists('dms_conf_attach_overrides_from_row')
        ? dms_conf_attach_overrides_from_row($confOverrideSource)
        : [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['conf_attach_override']) && is_array($_POST['conf_attach_override'])) {
        $confOverrideCurrent = [];
        foreach (['prep_signer', 'reviewer', 'approver', 'comms', 'cc'] as $st) {
            $v = strtolower(trim((string) ($_POST['conf_attach_override'][$st] ?? 'inherit')));
            $confOverrideCurrent[$st] = in_array($v, ['inherit', 'allow', 'deny'], true) ? $v : 'inherit';
        }
    }
    $confOverrideStages = [
        'prep_signer' => 'settings.conf_attach_prep_signer',
        'reviewer' => 'settings.conf_attach_reviewer',
        'approver' => 'settings.conf_attach_approver',
        'comms' => 'settings.conf_attach_comms',
        'cc' => 'settings.conf_attach_cc',
    ];
    $confOverrideOpts = [
        'allow' => 'conf.override_allow',
        'deny' => 'conf.override_deny',
    ];
    $confOverrideCustom = false;
    foreach (array_keys($confOverrideStages) as $st) {
        if (($confOverrideCurrent[$st] ?? 'inherit') !== 'inherit') {
            $confOverrideCustom = true;
            break;
        }
    }
    $GLOBALS['_dms_conf_override_state'] = [
        'current' => $confOverrideCurrent,
        'stages' => $confOverrideStages,
        'opts' => $confOverrideOpts,
        'custom' => $confOverrideCustom,
        'summary' => $confOverrideCustom
            ? __('conf.overrides_badge_custom')
            : __('conf.overrides_badge_default'),
    ];
}

$st = $GLOBALS['_dms_conf_override_state'];
$confOverrideCurrent = $st['current'];
$confOverrideStages = $st['stages'];
$confOverrideOpts = $st['opts'];
$confOverrideCustom = $st['custom'];
$confOverrideSummary = $st['summary'];
?>
<span class="conf-override-dropwrap" data-conf-overrides-wrap hidden>
    <button type="button"
            id="conf_override_pick_btn"
            class="btn btn-kff conf-override-beside-btn"
            aria-expanded="false"
            aria-controls="confAttachOverridePanel"
            aria-label="<?= e(__('conf.overrides_open')) ?>"
            title="<?= e(__('conf.overrides_open')) ?>">
        <i class="bi bi-file-earmark-lock" aria-hidden="true"></i>
        <span class="visually-hidden" id="conf_override_summary"><?= e($confOverrideSummary) ?></span>
    </button>
    <div class="conf-override-menu shadow" id="confAttachOverridePanel" role="dialog" aria-labelledby="confAttachOverridePanelTitle" hidden>
        <div class="conf-override-menu-head">
            <div class="conf-override-menu-head-row">
                <div class="conf-override-menu-title" id="confAttachOverridePanelTitle">
                    <i class="bi bi-file-earmark-lock me-1"></i><?= e(__('conf.overrides_title')) ?>
                </div>
                <button type="button"
                        class="btn-close conf-override-close"
                        id="conf_override_close_btn"
                        aria-label="<?= e(__('action.close')) ?>"></button>
            </div>
            <p class="conf-override-menu-help mb-0"><?= e(__('conf.overrides_help')) ?></p>
        </div>
        <div class="conf-override-menu-body">
            <table class="table table-sm conf-override-table mb-0">
                <thead>
                    <tr>
                        <th><?= e(__('conf.overrides_col_role')) ?></th>
                        <?php foreach ($confOverrideOpts as $opt => $optLabel): ?>
                            <th class="conf-override-opt-head"><?= e(__($optLabel)) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($confOverrideStages as $stageKey => $labelKey):
                        $cur = $confOverrideCurrent[$stageKey] ?? 'inherit';
                        ?>
                        <tr class="<?= $cur !== 'inherit' ? 'is-selected' : '' ?>" data-conf-override-row>
                            <td><span class="conf-override-role-name"><?= e(__($labelKey)) ?></span></td>
                            <?php foreach ($confOverrideOpts as $opt => $optLabel):
                                $oid = 'conf_attach_override_' . $stageKey . '_' . $opt;
                                ?>
                                <td class="conf-override-opt-cell">
                                    <div class="form-check conf-override-check conf-override-check-<?= e($opt) ?>">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="conf_attach_override[<?= e($stageKey) ?>]"
                                               id="<?= e($oid) ?>"
                                               value="<?= e($opt) ?>"
                                               data-conf-override-select
                                               data-conf-override-stage="<?= e($stageKey) ?>"
                                               aria-label="<?= e(__($labelKey) . ' — ' . __($optLabel)) ?>"
                                               <?= $cur === $opt ? 'checked' : '' ?>>
                                        <label class="form-check-label visually-hidden" for="<?= e($oid) ?>"><?= e(__($optLabel)) ?></label>
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="conf-override-menu-foot">
            <span class="badge text-bg-light text-dark border" id="confOverrideCount"></span>
        </div>
    </div>
</span>
<script>
(function () {
    if (window._dmsConfOverrideUiBound) return;
    window._dmsConfOverrideUiBound = true;
    var wrap = document.querySelector('[data-conf-overrides-wrap]');
    var btn = document.getElementById('conf_override_pick_btn');
    var panel = document.getElementById('confAttachOverridePanel');
    var sel = (wrap && wrap.closest('.conf-with-override') && wrap.closest('.conf-with-override').querySelector('[data-conf-level-select], select'))
        || document.getElementById('confidentiality')
        || document.getElementById('archive_confidentiality')
        || document.getElementById('register_confidentiality');
    if (!sel || !wrap || !btn || !panel) return;

    var overflowTouched = [];
    function unlockOverflow() {
        overflowTouched = [];
        var el = wrap.parentElement;
        while (el && el !== document.body) {
            var st = window.getComputedStyle(el);
            if (st.overflow === 'hidden' || st.overflowX === 'hidden' || st.overflowY === 'hidden') {
                overflowTouched.push({ el: el, overflow: el.style.overflow });
                el.style.overflow = 'visible';
            }
            el = el.parentElement;
        }
    }
    function restoreOverflow() {
        overflowTouched.forEach(function (item) {
            item.el.style.overflow = item.overflow;
        });
        overflowTouched = [];
    }
    function tipInstance() {
        if (!window.bootstrap || !bootstrap.Tooltip) return null;
        var existing = bootstrap.Tooltip.getInstance(btn);
        if (existing) return existing;
        return new bootstrap.Tooltip(btn, {
            container: 'body',
            placement: 'top',
            trigger: 'hover',
            fallbackPlacements: ['top']
        });
    }
    function hideTip() {
        var tip = window.bootstrap && bootstrap.Tooltip ? bootstrap.Tooltip.getInstance(btn) : null;
        if (tip) tip.hide();
        /* منع بقاء التلميح بسبب تركيز الزر بعد النقر */
        try { btn.blur(); } catch (err) {}
    }
    function setTipText(text) {
        btn.setAttribute('aria-label', text);
        btn.setAttribute('data-bs-original-title', text);
        btn.setAttribute('title', text);
        var tip = tipInstance();
        if (tip && typeof tip.setContent === 'function') {
            tip.setContent({ '.tooltip-inner': text });
        }
    }
    function setOpen(open) {
        panel.hidden = !open;
        wrap.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            hideTip();
            unlockOverflow();
        } else {
            restoreOverflow();
        }
    }
    function syncVis() {
        var v = (sel.value || 'normal').toLowerCase();
        var show = !!(v && v !== 'normal');
        wrap.hidden = !show;
        if (!show) setOpen(false);
    }
    sel.addEventListener('change', syncVis);
    syncVis();
    function ensureTip() {
        if (window.bootstrap && bootstrap.Tooltip) tipInstance();
        else setTimeout(ensureTip, 40);
    }
    ensureTip();

    btn.addEventListener('pointerdown', function () {
        hideTip();
    });
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        hideTip();
        setOpen(panel.hidden);
    });
    var closeBtn = document.getElementById('conf_override_close_btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            setOpen(false);
            hideTip();
        });
    }
    panel.addEventListener('click', function (e) {
        e.stopPropagation();
    });
    document.addEventListener('click', function () {
        if (!panel.hidden) setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) setOpen(false);
    });

    var summary = document.getElementById('conf_override_summary');
    var countEl = document.getElementById('confOverrideCount');
    var boxes = panel.querySelectorAll('[data-conf-override-select]');
    var labelDefault = <?= json_encode(__('conf.overrides_badge_default'), JSON_UNESCAPED_UNICODE) ?>;
    var labelCustom = <?= json_encode(__('conf.overrides_badge_custom'), JSON_UNESCAPED_UNICODE) ?>;
    var openTitle = <?= json_encode(__('conf.overrides_open'), JSON_UNESCAPED_UNICODE) ?>;
    var countTpl = <?= json_encode(__('conf.overrides_count'), JSON_UNESCAPED_UNICODE) ?>;

    function stageBoxes(stage) {
        return Array.prototype.filter.call(boxes, function (b) {
            return b.getAttribute('data-conf-override-stage') === stage;
        });
    }
    function syncSummary() {
        var custom = 0;
        var seen = {};
        boxes.forEach(function (b) {
            if (!b.checked) return;
            var stage = b.getAttribute('data-conf-override-stage') || '';
            if (seen[stage]) return;
            seen[stage] = true;
            custom += 1;
            var row = b.closest('[data-conf-override-row]');
            if (row) row.classList.add('is-selected');
        });
        panel.querySelectorAll('[data-conf-override-row]').forEach(function (row) {
            if (!row.querySelector('[data-conf-override-select]:checked')) {
                row.classList.remove('is-selected');
            }
        });
        if (summary) summary.textContent = custom > 0 ? labelCustom : labelDefault;
        btn.classList.toggle('is-custom', custom > 0);
        setTipText(custom > 0 ? labelCustom : openTitle);
        if (!panel.hidden) hideTip();
        if (countEl) countEl.textContent = countTpl.replace(':n', String(custom));
    }
    boxes.forEach(function (box) {
        box.addEventListener('change', function () {
            var stage = box.getAttribute('data-conf-override-stage') || '';
            if (box.checked) {
                stageBoxes(stage).forEach(function (other) {
                    if (other !== box) other.checked = false;
                });
            }
            syncSummary();
        });
    });
    syncSummary();
})();
</script>
