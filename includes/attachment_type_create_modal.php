<?php
/**
 * نافذة إنشاء نوع مرفق — تُضمَّن حيث يظهر زر الإضافة.
 * مهم: بدون <form> داخلي حتى لا يُكسر نموذج الوارد/الصادر الأب (HTML يمنع النماذج المتداخلة).
 */
if (empty($GLOBALS['_dms_attachment_type_modal'])) {
    $GLOBALS['_dms_attachment_type_modal'] = true;
} else {
    return;
}
$attModalNextSort = attachment_types_next_sort();
$attModalAction = base_url('api/attachment_type_create.php');
?>
<div class="modal fade" id="attachmentTypeCreateModal" tabindex="-1" aria-labelledby="attachmentTypeCreateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div id="attachmentTypeCreateForm" class="admin-form" data-action="<?= e($attModalAction) ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="attachmentTypeCreateModalLabel">
                        <i class="bi bi-plus-circle me-1"></i><?= e(__('lookups.new_attachment_type')) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.cancel')) ?>"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" id="attachmentTypeCreateError" role="alert"></div>
                    <div class="mb-3">
                        <label class="form-label" for="att_modal_name_ar"><?= e(__('lookups.name_ar')) ?> *</label>
                        <input type="text" name="name_ar" id="att_modal_name_ar" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="att_modal_name_en"><?= e(__('lookups.name_en')) ?></label>
                        <input type="text" name="name_en" id="att_modal_name_en" class="form-control" dir="ltr">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="att_modal_code"><?= e(__('lookups.code')) ?></label>
                        <input type="text" name="code" id="att_modal_code" class="form-control" dir="ltr" placeholder="auto">
                        <div class="form-text"><?= e(__('lookups.code_hint')) ?></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="att_modal_sort"><?= e(__('lookups.sort')) ?></label>
                        <input type="text" id="att_modal_sort" class="form-control" value="<?= (int) $attModalNextSort ?>" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="button" class="btn btn-kff" id="attachmentTypeCreateSubmit">
                        <i class="bi bi-check2 me-1"></i><?= e(__('action.save')) ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
  var modalEl = document.getElementById('attachmentTypeCreateModal');
  var form = document.getElementById('attachmentTypeCreateForm');
  if (!modalEl || !form || form.dataset.ready) { return; }
  form.dataset.ready = '1';

  // خارج النموذج الأب حتى لا يتأثر تخطيط DOM
  if (modalEl.parentNode !== document.body) {
    document.body.appendChild(modalEl);
  }

  var errBox = document.getElementById('attachmentTypeCreateError');
  var sortInp = document.getElementById('att_modal_sort');
  var submitBtn = document.getElementById('attachmentTypeCreateSubmit');
  var nameAr = document.getElementById('att_modal_name_ar');

  function resetFields() {
    form.querySelectorAll('input[name]').forEach(function (inp) {
      if (inp.type === 'hidden' && inp.name === 'csrf_token') return;
      inp.value = '';
    });
  }

  function buildBody() {
    var fd = new FormData();
    form.querySelectorAll('input[name]').forEach(function (inp) {
      fd.append(inp.name, inp.value || '');
    });
    return fd;
  }

  modalEl.addEventListener('show.bs.modal', function () {
    if (errBox) {
      errBox.classList.add('d-none');
      errBox.textContent = '';
    }
  });

  function submitCreate() {
    if (errBox) {
      errBox.classList.add('d-none');
      errBox.textContent = '';
    }
    if (nameAr && !String(nameAr.value || '').trim()) {
      if (errBox) {
        errBox.textContent = <?= json_encode(__('common.name_required'), JSON_UNESCAPED_UNICODE) ?>;
        errBox.classList.remove('d-none');
      }
      if (nameAr) nameAr.focus();
      return;
    }
    if (submitBtn) { submitBtn.disabled = true; }
    var action = form.getAttribute('data-action') || '';
    fetch(action, {
      method: 'POST',
      body: buildBody(),
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) { return res.json().then(function (data) { return { okHttp: res.ok, data: data }; }); })
      .then(function (result) {
        var data = result.data || {};
        if (!data.ok) {
          throw new Error(data.error || 'error');
        }
        resetFields();
        if (sortInp && data.item && data.item.sort_order) {
          sortInp.value = String(Number(data.item.sort_order) + 10);
        }
        document.dispatchEvent(new CustomEvent('dms-attachment-type-created', { detail: data.item }));
        if (window.bootstrap && bootstrap.Modal) {
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
      })
      .catch(function (err) {
        if (errBox) {
          errBox.textContent = err.message || '';
          errBox.classList.remove('d-none');
        }
      })
      .finally(function () {
        if (submitBtn) { submitBtn.disabled = false; }
      });
  }

  if (submitBtn) {
    submitBtn.addEventListener('click', submitCreate);
  }
  if (nameAr) {
    nameAr.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        submitCreate();
      }
    });
  }
})();
</script>
