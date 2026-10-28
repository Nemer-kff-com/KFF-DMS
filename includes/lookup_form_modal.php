<?php
/**
 * نافذة منبثقة لإضافة/تعديل مسميات القوائم.
 */
$lookupModalSaveUrl = base_url('api/lookup_save.php');
?>
<div class="modal fade" id="lookupFormModal" tabindex="-1" aria-labelledby="lookupFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="lookupFormModalForm" class="admin-form" method="post" action="<?= e($lookupModalSaveUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="kind" id="lookup_modal_kind" value="">
                <input type="hidden" name="id" id="lookup_modal_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="lookupFormModalLabel">
                        <i class="bi bi-pencil-square me-1" id="lookup_modal_icon"></i>
                        <span id="lookup_modal_title_text"><?= e(__('lookups.edit')) ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="lookupFormModalError" role="alert"></div>
                    <div class="mb-3">
                        <label class="form-label" for="lookup_modal_sort"><?= e(__('lookups.sort')) ?></label>
                        <input type="number" name="sort_order" id="lookup_modal_sort" class="form-control" value="0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="lookup_modal_name_ar" id="lookup_modal_name_ar_label"><?= e(__('lookups.name_ar')) ?> *</label>
                        <input type="text" name="name_ar" id="lookup_modal_name_ar" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="lookup_modal_name_en"><?= e(__('lookups.name_en')) ?> <span id="lookup_modal_name_en_req">*</span></label>
                        <input type="text" name="name_en" id="lookup_modal_name_en" class="form-control" dir="ltr">
                    </div>
                    <div class="mb-3 d-none" id="lookup_modal_iso_wrap">
                        <label class="form-label" for="lookup_modal_iso2"><?= e(__('lookups.iso2')) ?></label>
                        <input type="text" name="iso2" id="lookup_modal_iso2" class="form-control" dir="ltr" maxlength="8" placeholder="SA">
                    </div>
                    <div class="mb-3 d-none" id="lookup_modal_region_wrap">
                        <label class="form-label" for="lookup_modal_region_id"><?= e(__('lookups.region_id')) ?></label>
                        <input type="text" name="region_id" id="lookup_modal_region_id" class="form-control" dir="ltr" maxlength="8">
                    </div>
                    <div class="mb-3 d-none" id="lookup_modal_country_wrap">
                        <label class="form-label" for="lookup_modal_country_id"><?= e(__('archive.xf.country')) ?></label>
                        <select name="country_id" id="lookup_modal_country_id" class="form-select">
                            <option value=""><?= e(__('common.none')) ?></option>
                        </select>
                    </div>
                    <div class="mb-3" id="lookup_modal_code_wrap">
                        <label class="form-label" for="lookup_modal_code"><?= e(__('lookups.code')) ?></label>
                        <input type="text" name="code" id="lookup_modal_code" class="form-control" dir="ltr" placeholder="auto">
                        <div class="form-text" id="lookup_modal_code_hint"><?= e(__('lookups.code_hint')) ?></div>
                    </div>
                    <div class="d-none" id="lookup_modal_active_wrap">
                        <div class="d-flex align-items-start gap-2">
                            <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="is_active" id="lookup_modal_is_active" value="1" checked>
                            <label class="form-check-label mb-0" for="lookup_modal_is_active"><?= e(__('common.active')) ?></label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="submit" class="btn btn-kff" id="lookupFormModalSubmit">
                        <i class="bi bi-check2 me-1"></i><span id="lookup_modal_save_label"><?= e(__('action.save')) ?></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
  var modalEl = document.getElementById('lookupFormModal');
  var form = document.getElementById('lookupFormModalForm');
  if (!modalEl || !form || form.dataset.ready) return;
  form.dataset.ready = '1';

  var errBox = document.getElementById('lookupFormModalError');
  var submitBtn = document.getElementById('lookupFormModalSubmit');
  var kindInp = document.getElementById('lookup_modal_kind');
  var idInp = document.getElementById('lookup_modal_id');
  var sortInp = document.getElementById('lookup_modal_sort');
  var nameAr = document.getElementById('lookup_modal_name_ar');
  var nameEn = document.getElementById('lookup_modal_name_en');
  var nameEnReq = document.getElementById('lookup_modal_name_en_req');
  var codeWrap = document.getElementById('lookup_modal_code_wrap');
  var codeInp = document.getElementById('lookup_modal_code');
  var codeHint = document.getElementById('lookup_modal_code_hint');
  var activeWrap = document.getElementById('lookup_modal_active_wrap');
  var activeInp = document.getElementById('lookup_modal_is_active');
  var titleText = document.getElementById('lookup_modal_title_text');
  var saveLabel = document.getElementById('lookup_modal_save_label');
  var iconEl = document.getElementById('lookup_modal_icon');
  var isoWrap = document.getElementById('lookup_modal_iso_wrap');
  var isoInp = document.getElementById('lookup_modal_iso2');
  var regionWrap = document.getElementById('lookup_modal_region_wrap');
  var regionInp = document.getElementById('lookup_modal_region_id');
  var countryWrap = document.getElementById('lookup_modal_country_wrap');
  var countryInp = document.getElementById('lookup_modal_country_id');

    var i18n = {
    create: <?= json_encode(__('action.save'), JSON_UNESCAPED_UNICODE) ?>,
    update: <?= json_encode(__('action.save_changes'), JSON_UNESCAPED_UNICODE) ?>,
    newCategory: <?= json_encode(__('categories.new'), JSON_UNESCAPED_UNICODE) ?>,
    newPriority: <?= json_encode(__('lookups.new_priority'), JSON_UNESCAPED_UNICODE) ?>,
    newConf: <?= json_encode(__('lookups.new_confidentiality'), JSON_UNESCAPED_UNICODE) ?>,
    newStatus: <?= json_encode(__('lookups.new_status'), JSON_UNESCAPED_UNICODE) ?>,
    newType: <?= json_encode(__('lookups.new_type'), JSON_UNESCAPED_UNICODE) ?>,
    newCatalog: <?= json_encode(__('lookups.new_catalog'), JSON_UNESCAPED_UNICODE) ?>,
    newAttachment: <?= json_encode(__('lookups.new_attachment_type'), JSON_UNESCAPED_UNICODE) ?>,
    edit: <?= json_encode(__('lookups.edit'), JSON_UNESCAPED_UNICODE) ?>
  };

  function setError(msg) {
    if (!errBox) return;
    if (msg) {
      errBox.textContent = msg;
      errBox.classList.remove('d-none');
    } else {
      errBox.textContent = '';
      errBox.classList.add('d-none');
    }
  }

  function openLookupModal(opts) {
    try {
    opts = opts || {};
    var kind = opts.kind || '';
    var id = parseInt(opts.id || '0', 10) || 0;
    var isEdit = id > 0;
    if (kindInp) kindInp.value = kind;
    if (idInp) idInp.value = String(id);
    if (sortInp) sortInp.value = opts.sort_order != null ? String(opts.sort_order) : '0';
    if (nameAr) nameAr.value = opts.name_ar || opts.name || '';
    if (nameEn) nameEn.value = opts.name_en || '';
    if (isoInp) isoInp.value = opts.iso2 || '';
    if (regionInp) regionInp.value = opts.region_id || '';
    if (codeInp && !isEdit) codeInp.value = '';
    var isCatalog = kind.indexOf('catalog_') === 0;
    var catalogKey = isCatalog ? kind.slice(8) : '';
    if (isoWrap) isoWrap.classList.toggle('d-none', catalogKey !== 'countries');
    if (regionWrap) regionWrap.classList.toggle('d-none', catalogKey !== 'countries');
    if (countryWrap) countryWrap.classList.toggle('d-none', catalogKey !== 'city');
    if (catalogKey === 'city' && countryInp) {
      var optsList = window.dmsCatalogCountries || [];
      var keep = countryInp.querySelector('option[value=""]');
      countryInp.innerHTML = '';
      if (keep) countryInp.appendChild(keep);
      else {
        var emptyOpt = document.createElement('option');
        emptyOpt.value = '';
        emptyOpt.textContent = <?= json_encode(__('common.none'), JSON_UNESCAPED_UNICODE) ?>;
        countryInp.appendChild(emptyOpt);
      }
      optsList.forEach(function (c) {
        var o = document.createElement('option');
        o.value = String(c.id);
        o.textContent = c.name || '';
        countryInp.appendChild(o);
      });
      countryInp.value = opts.country_id ? String(opts.country_id) : '';
    }
    if (activeInp) activeInp.checked = opts.is_active === undefined ? true : String(opts.is_active) !== '0';
    setError('');

    var needsCode = kind !== 'categories' && kind.indexOf('catalog_') !== 0;
    if (codeWrap) codeWrap.classList.toggle('d-none', !needsCode);
    if (needsCode && codeInp) {
      var lockCode = isEdit;
      codeInp.readOnly = lockCode;
      if (codeHint) codeHint.classList.toggle('d-none', lockCode);
    }

    var nameEnRequired = (kind === 'priorities' || kind === 'confidentiality' || kind === 'statuses' || kind === 'attachment_types');
    if (nameEn) nameEn.required = nameEnRequired;
    if (nameEnReq) nameEnReq.classList.toggle('d-none', !nameEnRequired);

    if (activeWrap) activeWrap.classList.toggle('d-none', !isEdit && !isCatalog);
    if (!isEdit && activeInp) activeInp.checked = true;

    if (!isEdit) {
      if (kind === 'categories') titleText.textContent = i18n.newCategory;
      else if (kind === 'priorities') titleText.textContent = i18n.newPriority;
      else if (kind === 'confidentiality') titleText.textContent = i18n.newConf;
      else if (kind === 'statuses') titleText.textContent = i18n.newStatus;
      else if (kind === 'attachment_types') titleText.textContent = i18n.newAttachment;
      else if (isCatalog) titleText.textContent = i18n.newCatalog;
      else titleText.textContent = i18n.newType;
      if (iconEl) iconEl.className = 'bi bi-plus-circle me-1';
      if (saveLabel) saveLabel.textContent = i18n.create;
    } else {
      if (titleText) titleText.textContent = i18n.edit + (opts.code ? ' — ' + opts.code : '');
      if (iconEl) iconEl.className = 'bi bi-pencil-square me-1';
      if (saveLabel) saveLabel.textContent = i18n.update;
    }

    if (window.bootstrap && bootstrap.Modal) {
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
      setTimeout(function () { if (nameAr) nameAr.focus(); }, 200);
    }
    } catch (err) {
      setError(err && err.message ? err.message : <?= json_encode(__('lookups.save_fail'), JSON_UNESCAPED_UNICODE) ?>);
    }
  }

  window.dmsOpenLookupModal = openLookupModal;

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-lookup-modal]');
    if (!btn) return;
    ev.preventDefault();
    openLookupModal({
      kind: btn.getAttribute('data-kind') || '',
      id: btn.getAttribute('data-id') || '0',
      sort_order: btn.getAttribute('data-sort') || '0',
      name_ar: btn.getAttribute('data-name-ar') || '',
      name_en: btn.getAttribute('data-name-en') || '',
      code: btn.getAttribute('data-code') || '',
      iso2: btn.getAttribute('data-iso2') || '',
      region_id: btn.getAttribute('data-region-id') || '',
      country_id: btn.getAttribute('data-country-id') || '',
      is_active: btn.getAttribute('data-active') || '1'
    });
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    setError('');
    if (submitBtn) submitBtn.disabled = true;
    var fd = new FormData(form);
    if (codeInp && (codeInp.readOnly || codeInp.disabled)) {
      // لا ترسل رمزاً قابلاً للتعديل بالخطأ عند التعديل
    }
    if (activeWrap && activeWrap.classList.contains('d-none')) {
      fd.delete('is_active');
    } else if (activeInp && !activeInp.checked) {
      fd.delete('is_active');
    }
    fetch(form.action, {
      method: 'POST',
      body: fd,
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(function (res) {
      return res.text().then(function (text) {
        var data = {};
        try { data = text ? JSON.parse(text) : {}; } catch (err) {
          throw new Error(<?= json_encode(__('lookups.save_fail'), JSON_UNESCAPED_UNICODE) ?>);
        }
        return { okHttp: res.ok, data: data };
      });
    }).then(function (result) {
      var data = result.data || {};
      if (!data.ok) throw new Error(data.error || 'error');
      if (window.bootstrap && bootstrap.Modal) {
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      }
      window.location.reload();
    }).catch(function (err) {
      setError(err.message || '');
    }).finally(function () {
      if (submitBtn) submitBtn.disabled = false;
    });
  });
})();
</script>
