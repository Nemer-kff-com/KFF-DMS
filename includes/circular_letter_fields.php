<?php
/**
 * طريقة التعميم: ملف جاهز أو خطاب إلكتروني.
 * يتوقع $item و $lockedIssued و $allDepartments.
 */
if (!function_exists('letter_templates_list')) {
    require_once __DIR__ . '/../config/letter_templates.php';
}
$item = $item ?? [];
$lockedIssued = !empty($lockedIssued);
$user = $user ?? (function_exists('current_user') ? current_user() : null);
$contentMode = 'file';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['content_mode'])) {
    $contentMode = (string) $_POST['content_mode'];
} else {
    $contentMode = (string) ($item['content_mode'] ?? 'file');
}
if (!in_array($contentMode, ['file', 'electronic'], true)) {
    $contentMode = 'file';
}
$circularElectronic = $contentMode === 'electronic';
$letterAr = (string) (
    (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? ($_POST['letter_body'] ?? null) : null)
    ?? ($item['letter_body'] ?? '')
);
$letterEn = (string) (
    (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? ($_POST['letter_body_en'] ?? null) : null)
    ?? ($item['letter_body_en'] ?? '')
);
$templates = (!$lockedIssued && function_exists('letter_templates_list'))
    ? letter_templates_list($user, true)
    : [];
$previewDepts = [];
if ($circularElectronic && !empty($item['id']) && function_exists('get_circular_departments')) {
    $previewDepts = get_circular_departments((int) $item['id']);
}
?>
<div class="admin-form-section" id="circular-letter-mode">
    <div class="admin-form-section-title"><?= e(__('circular.mode_title')) ?></div>
    <?php if ($lockedIssued): ?>
        <input type="hidden" name="content_mode" value="<?= e($contentMode) ?>">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="badge text-bg-light border">
                <i class="bi <?= $circularElectronic ? 'bi-file-earmark-text' : 'bi-paperclip' ?> me-1"></i>
                <?= e($circularElectronic ? __('circular.mode_electronic') : __('circular.mode_file')) ?>
            </span>
        </div>
        <?php if ($circularElectronic && function_exists('circular_has_letter') && circular_has_letter($item)): ?>
            <div class="circular-letter-paper">
                <?= circular_letter_preview_html($item, $previewDepts) ?>
            </div>
        <?php elseif ($circularElectronic): ?>
            <p class="text-muted mb-0"><?= e(__('circular.letter_empty')) ?></p>
        <?php endif; ?>
    <?php else: ?>
        <div class="circular-mode-grid">
            <label class="circular-mode-card<?= $circularElectronic ? '' : ' is-on' ?>" for="circular_mode_file">
                <input class="form-check-input" type="radio" name="content_mode" id="circular_mode_file" value="file" <?= $circularElectronic ? '' : 'checked' ?>>
                <span class="circular-mode-icon" aria-hidden="true"><i class="bi bi-paperclip"></i></span>
                <span>
                    <span class="circular-mode-title"><?= e(__('circular.mode_file')) ?></span>
                    <span class="circular-mode-hint"><?= e(__('circular.mode_file_hint')) ?></span>
                </span>
            </label>
            <label class="circular-mode-card<?= $circularElectronic ? ' is-on' : '' ?>" for="circular_mode_electronic">
                <input class="form-check-input" type="radio" name="content_mode" id="circular_mode_electronic" value="electronic" <?= $circularElectronic ? 'checked' : '' ?>>
                <span class="circular-mode-icon" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span>
                <span>
                    <span class="circular-mode-title"><?= e(__('circular.mode_electronic')) ?></span>
                    <span class="circular-mode-hint"><?= e(__('circular.mode_electronic_hint')) ?></span>
                </span>
            </label>
        </div>

        <div id="circular-letter-editor" class="<?= $circularElectronic ? '' : 'd-none' ?>">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 mb-2">
                <span class="fw-semibold"><?= e(__('corr.letter_body')) ?></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="circular-letter-preview-btn">
                    <i class="bi bi-eye me-1"></i><?= e(__('circular.preview')) ?>
                </button>
            </div>
            <?php if ($templates): ?>
            <div class="mb-2">
                <label class="form-label" for="circular_letter_template_id"><?= e(__('tpl.apply')) ?></label>
                <select id="circular_letter_template_id" class="form-select">
                    <option value=""><?= e(__('common.none')) ?></option>
                    <?php foreach ($templates as $tpl): ?>
                        <option value="<?= (int) $tpl['id'] ?>"
                                data-body="<?= e((string) ($tpl['body'] ?? '')) ?>"
                                data-body-en="<?= e((string) ($tpl['body_en'] ?? '')) ?>"><?= e(letter_template_label($tpl)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <?php render_letter_lang_editor('letter_body', 'letter_body_en', $letterAr, $letterEn, 8, false, [
                '{number}' => __('circular.token_number_tip'),
                '{subject}' => __('circular.token_subject_tip'),
            ], [
                '{title}',
                '{name}',
                '{honorific}',
                '{honored_name}',
                '{job}',
                '{org}',
                '{logo}',
            ], __('circular.letter_hint'), letter_signer_insert_tokens()); ?>
        </div>

        <div class="modal fade" id="circularLetterPreviewModal" tabindex="-1" aria-hidden="true"
             data-preview-url="<?= e(base_url('circulars/letter_preview.php')) ?>"
             data-preview-fail="<?= e(__('circular.preview_fail')) ?>"
             data-preview-empty="<?= e(__('circular.preview_empty')) ?>">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content letter-preview-modal">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-eye me-1"></i><?= e(__('circular.preview')) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                    </div>
                    <div class="modal-body">
                        <p class="admin-form-hint mb-3"><?= e(__('circular.preview_hint')) ?></p>
                        <div class="letter-preview-sheet" data-circular-preview-body></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('tpl.preview_close')) ?></button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php if (!$lockedIssued): ?>
<script>
(function () {
    var fileRadio = document.getElementById('circular_mode_file');
    var elecRadio = document.getElementById('circular_mode_electronic');
    var editor = document.getElementById('circular-letter-editor');
    var cards = document.querySelectorAll('#circular-letter-mode .circular-mode-card');

    function syncMode() {
        var electronic = !!(elecRadio && elecRadio.checked);
        if (editor) editor.classList.toggle('d-none', !electronic);
        cards.forEach(function (card) {
            var input = card.querySelector('input');
            card.classList.toggle('is-on', !!(input && input.checked));
        });
        var title = document.getElementById('circular-file-title');
        var hint = document.getElementById('circular-file-hint');
        if (title) {
            title.textContent = electronic ? (title.getAttribute('data-electronic') || '') : (title.getAttribute('data-file') || '');
        }
        if (hint) {
            hint.textContent = electronic ? (hint.getAttribute('data-electronic') || '') : (hint.getAttribute('data-file') || '');
        }
        syncIssueButton();
    }
    function syncIssueButton() {
        var btn = document.getElementById('circular_issue_btn');
        var input = document.getElementById('circular_attachments');
        if (!btn || !input) return;
        var electronic = !!(elecRadio && elecRadio.checked);
        var hasFile = !!(input.files && input.files.length);
        var blocked = !electronic && !hasFile;
        btn.disabled = blocked;
        btn.style.pointerEvents = blocked ? 'none' : '';
        var wrap = document.getElementById('circular_issue_wrap');
        if (!wrap) return;
        var tip = wrap.getAttribute('data-file-tip') || '';
        var inst = window.bootstrap && bootstrap.Tooltip ? bootstrap.Tooltip.getInstance(wrap) : null;
        if (blocked) {
            wrap.setAttribute('tabindex', '0');
            wrap.setAttribute('data-bs-toggle', 'tooltip');
            wrap.setAttribute('title', tip);
            if (window.bootstrap && bootstrap.Tooltip) {
                if (inst) inst.setContent({ '.tooltip-inner': tip });
                else bootstrap.Tooltip.getOrCreateInstance(wrap);
            }
        } else {
            if (inst) inst.dispose();
            wrap.removeAttribute('title');
            wrap.removeAttribute('data-bs-original-title');
            wrap.removeAttribute('tabindex');
            wrap.removeAttribute('data-bs-toggle');
        }
    }
    if (fileRadio) fileRadio.addEventListener('change', syncMode);
    if (elecRadio) elecRadio.addEventListener('change', syncMode);
    document.addEventListener('dms-file-change', syncIssueButton);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', syncMode);
    } else {
        syncMode();
    }

    var tpl = document.getElementById('circular_letter_template_id');
    var ta = document.getElementById('letter_body');
    var taEn = document.getElementById('letter_body_en');
    if (tpl && ta) {
        tpl.addEventListener('change', function () {
            var opt = tpl.options[tpl.selectedIndex];
            var body = opt ? (opt.getAttribute('data-body') || '') : '';
            var bodyEn = opt ? (opt.getAttribute('data-body-en') || '') : '';
            if (body) ta.value = body;
            if (taEn && bodyEn) taEn.value = bodyEn;
        });
    }

    var previewBtn = document.getElementById('circular-letter-preview-btn');
    var modalEl = document.getElementById('circularLetterPreviewModal');
    if (!previewBtn || !modalEl) return;
    var previewUrl = modalEl.getAttribute('data-preview-url') || '';
    var previewFail = modalEl.getAttribute('data-preview-fail') || '';
    var previewEmpty = modalEl.getAttribute('data-preview-empty') || '';
    var form = previewBtn.closest('form');

    previewBtn.addEventListener('click', function () {
        var bodyEl = modalEl.querySelector('[data-circular-preview-body]');
        var ar = ta ? ta.value.trim() : '';
        var en = taEn ? taEn.value.trim() : '';
        if (!ar && !en) {
            if (window.DmsSwal && typeof window.DmsSwal.warning === 'function') {
                window.DmsSwal.warning(previewEmpty);
            } else {
                window.alert(previewEmpty);
            }
            return;
        }
        if (bodyEl) bodyEl.innerHTML = '<p class="text-muted mb-0">…</p>';
        var modal = window.bootstrap && bootstrap.Modal ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
        if (modal) modal.show();
        var data = new FormData();
        function addField(name) {
            if (!form) return;
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            data.set(name, el.value || '');
        }
        var csrf = form ? form.querySelector('input[name="csrf_token"]') : null;
        if (csrf) data.set('csrf_token', csrf.value);
        addField('subject');
        addField('circular_date');
        addField('department_id');
        addField('letter_body');
        addField('letter_body_en');
        var audience = form ? form.querySelector('input[name="audience"]:checked') : null;
        data.set('audience', audience ? audience.value : 'all');
        if (form) {
            form.querySelectorAll('input[name="audience_department_ids[]"]:checked').forEach(function (box) {
                data.append('audience_department_ids[]', box.value);
            });
        }
        fetch(previewUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: data
        }).then(function (res) {
            return res.json().then(function (json) { return { ok: res.ok, data: json }; });
        }).then(function (pack) {
            if (!pack.ok || !pack.data || !pack.data.ok) {
                throw new Error((pack.data && pack.data.error) || previewFail);
            }
            if (bodyEl) bodyEl.innerHTML = pack.data.html || '';
        }).catch(function (err) {
            if (bodyEl) {
                bodyEl.innerHTML = '<span class="text-danger">' + (err && err.message ? err.message : previewFail) + '</span>';
            }
        });
    });
})();
</script>
<?php endif; ?>
