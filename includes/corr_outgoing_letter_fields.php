<?php
/**
 * قسم نص الخطاب + قالب + معاينة البريد — للصادر الفردي (إنشاء/تعديل).
 * يتوقع اختيارياً: $corr, $user
 */
if (!function_exists('letter_templates_list')) {
    require_once __DIR__ . '/../config/letter_templates.php';
}
$user = $user ?? (function_exists('current_user') ? current_user() : null);
$templates = letter_templates_list($user, true);
$letterAr = (string) (
    $_POST['letter_body']
    ?? ($corr['letter_body'] ?? '')
);
$letterEn = (string) (
    $_POST['letter_body_en']
    ?? ($corr['letter_body_en'] ?? '')
);
$letterSectionOpen = !empty($_POST['intend_electronic'])
    || (!empty($corr['intend_electronic']) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST');
?>
<div class="admin-form-section<?= $letterSectionOpen ? '' : ' d-none' ?>" id="outgoing-letter-section"
     data-depends-on="intend_electronic">
    <div class="admin-form-section-title d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><?= e(__('bulk.letter_body')) ?></span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="outgoing-email-preview-btn"
                title="<?= e(__('send.email_preview')) ?>">
            <i class="bi bi-envelope-open me-1"></i><?= e(__('send.email_preview')) ?>
        </button>
    </div>
    <?php if ($templates): ?>
    <div class="mb-2">
        <label class="form-label" for="letter_template_id"><?= e(__('tpl.apply')) ?></label>
        <select id="letter_template_id" class="form-select" data-letter-templates>
            <option value=""><?= e(__('common.none')) ?></option>
            <?php foreach ($templates as $tpl): ?>
                <option value="<?= (int) $tpl['id'] ?>"
                        data-body="<?= e((string) ($tpl['body'] ?? '')) ?>"
                        data-body-en="<?= e((string) ($tpl['body_en'] ?? '')) ?>"><?= e(letter_template_label($tpl)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <?php render_letter_lang_editor('letter_body', 'letter_body_en', $letterAr, $letterEn, 8, false, [], [], '', letter_signer_insert_tokens()); ?>
</div>

<div class="modal fade" id="outgoingEmailPreviewModal" tabindex="-1" aria-hidden="true"
     data-preview-url="<?= e(base_url('outgoing/draft_preview.php')) ?>"
     data-preview-fail="<?= e(__('send.email_preview_fail')) ?>"
     data-preview-loading="<?= e(__('send.email_preview_loading')) ?>"
     data-need-entity="<?= e(__('corr.entity_required_preview')) ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope-open me-1"></i><?= e(__('send.email_preview_title')) ?>
                    <span class="text-muted fw-normal small" data-email-preview-ref></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('send.email_preview_close')) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="small text-muted"><?= e(__('send.email_preview_recipient')) ?></div>
                    <div class="fw-semibold" data-email-preview-recipient>—</div>
                </div>
                <div class="mb-3">
                    <div class="small text-muted"><?= e(__('send.email_preview_to')) ?></div>
                    <div class="fw-semibold email-preview-to" data-email-preview-to>—</div>
                </div>
                <div class="mb-3">
                    <div class="small text-muted"><?= e(__('send.email_preview_subject')) ?></div>
                    <div class="fw-semibold" data-email-preview-subject>—</div>
                </div>
                <div>
                    <div class="small text-muted mb-2"><?= e(__('send.email_preview_body')) ?></div>
                    <div class="border rounded p-3 bg-white batch-email-preview-body" data-email-preview-body>
                        <span class="text-muted"><?= e(__('send.email_preview_loading')) ?></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('send.email_preview_close')) ?></button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
  var section = document.getElementById('outgoing-letter-section');
  var box = document.getElementById('intend_electronic');
  if (section && box) {
    function syncLetterSection() {
      var on = !!box.checked;
      section.classList.toggle('d-none', !on);
      section.querySelectorAll('input, select, textarea').forEach(function (el) {
        el.disabled = !on;
      });
    }
    box.addEventListener('change', syncLetterSection);
    syncLetterSection();
  }

  var tpl = document.querySelector('#outgoing-letter-section [data-letter-templates]');
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

  var previewBtn = document.getElementById('outgoing-email-preview-btn');
  var modalEl = document.getElementById('outgoingEmailPreviewModal');
  if (!previewBtn || !modalEl) return;
  var previewUrl = modalEl.getAttribute('data-preview-url') || '';
  var previewFail = modalEl.getAttribute('data-preview-fail') || '';
  var previewLoading = modalEl.getAttribute('data-preview-loading') || '';
  var needEntity = modalEl.getAttribute('data-need-entity') || '';
  var form = previewBtn.closest('form');

  function fieldVal(name) {
    if (!form) return '';
    var el = form.querySelector('[name="' + name + '"]');
    if (!el) return '';
    if (el.type === 'checkbox') return el.checked ? '1' : '';
    return el.value || '';
  }

  previewBtn.addEventListener('click', function () {
    var entityId = fieldVal('entity_id');
    if (!entityId) {
      if (window.DmsSwal && typeof window.DmsSwal.warning === 'function') {
        window.DmsSwal.warning(needEntity);
      } else {
        window.alert(needEntity);
      }
      return;
    }
    var bodyEl = modalEl.querySelector('[data-email-preview-body]');
    var toEl = modalEl.querySelector('[data-email-preview-to]');
    var nameEl = modalEl.querySelector('[data-email-preview-recipient]');
    var subEl = modalEl.querySelector('[data-email-preview-subject]');
    var refEl = modalEl.querySelector('[data-email-preview-ref]');
    if (bodyEl) bodyEl.innerHTML = '<span class="text-muted">' + previewLoading + '</span>';
    if (toEl) toEl.textContent = '—';
    if (nameEl) nameEl.textContent = '—';
    if (subEl) subEl.textContent = '—';
    if (refEl) refEl.textContent = '';
    var modal = window.bootstrap && bootstrap.Modal ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    if (modal) modal.show();

    var data = new FormData();
    var csrf = form ? form.querySelector('input[name="csrf_token"]') : null;
    if (csrf) data.set('csrf_token', csrf.value);
    data.set('entity_id', entityId);
    data.set('subject', fieldVal('subject'));
    data.set('corr_date', fieldVal('corr_date'));
    data.set('department_id', fieldVal('department_id'));
    data.set('issuing_department_id', fieldVal('issuing_department_id'));
    data.set('letter_body', fieldVal('letter_body'));
    data.set('letter_body_en', fieldVal('letter_body_en'));
    data.set('rsvp_required', fieldVal('rsvp_required') || fieldVal('requires_rsvp'));
    data.set('intend_electronic', fieldVal('intend_electronic') || '1');
    var corrId = form && form.getAttribute('data-corr-id');
    if (corrId) data.set('id', corrId);
    var refNumber = form && form.getAttribute('data-corr-ref');
    if (refNumber) data.set('ref_number', refNumber);

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
      if (nameEl) nameEl.textContent = pack.data.recipient || '—';
      if (toEl) toEl.textContent = pack.data.to || '—';
      if (subEl) subEl.textContent = pack.data.subject || '—';
      if (refEl) refEl.textContent = pack.data.ref ? ('— ' + pack.data.ref) : '';
      if (bodyEl) bodyEl.innerHTML = pack.data.html || '';
    }).catch(function (err) {
      if (bodyEl) {
        bodyEl.innerHTML = '<span class="text-danger">' + (err && err.message ? err.message : previewFail) + '</span>';
      }
    });
  });
})();
</script>
