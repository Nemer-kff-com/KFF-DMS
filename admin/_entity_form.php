<?php
/**
 * حقول نموذج جهة المراسلة (إضافة/تعديل)
 * يتطلب: $values, $isEdit, $canManageAllEntities, $departments, $user, $entityScopeReady
 * اختياري: $entityFormPrefix, $entityFormInModal, $entityFormCompact, $isSystemAdmin
 */
$entityFormPrefix = $entityFormPrefix ?? '';
$entityFormInModal = !empty($entityFormInModal);
$entityFormModalCompact = !empty($entityFormModalCompact) && $entityFormInModal && !$isEdit;
$entityFormCompactEdit = !empty($entityFormCompact) && $isEdit && !$entityFormInModal;
$entityFormUseCompact = $entityFormCompactEdit || $entityFormModalCompact;
$entityFormHideActions = !empty($entityFormHideActions);
$isSystemAdmin = isset($isSystemAdmin)
    ? (bool) $isSystemAdmin
    : (function_exists('can_manage_users') && can_manage_users($user ?? null));
$efId = static function (string $base) use ($entityFormPrefix): string {
    return $entityFormPrefix . $base;
};

if ($entityFormUseCompact): ?>
<div class="entity-form-compact entity-form-stack entity-form-narrow entity-form-modal-compact<?= $entityFormInModal ? '' : ' entity-form-on-page' ?>">
    <?php
    $entityLetterSectionLast = false;
    $entityLetterCompact = true;
    $entityLetterHideTitle = true;
    $entityLetterIncludeJob = false;
    $entityLetterWide = true;
    require __DIR__ . '/_entity_letter_fields.php';
    ?>

    <div class="admin-form-section entity-side-panel">
        <div class="admin-form-section-title"><?= e(__('entities.section_basic')) ?></div>
        <div class="row g-3 entity-form-field-row">
            <div class="col-12">
                <label class="form-label" for="<?= e($efId('entity_name_ar')) ?>"><?= e(__('entities.name_ar')) ?></label>
                <input type="text" name="name_ar" id="<?= e($efId('entity_name_ar')) ?>" class="form-control" value="<?= e((string) ($values['name_ar'] ?? '')) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="<?= e($efId('entity_job_title')) ?>"><?= e(__('entities.job_title')) ?></label>
                <input type="text" name="job_title" id="<?= e($efId('entity_job_title')) ?>" class="form-control"
                       value="<?= e((string) ($values['job_title'] ?? '')) ?>" placeholder="<?= e(__('entities.job_title_ph')) ?>">
            </div>
        </div>
    </div>

    <div class="admin-form-section entity-side-panel">
        <div class="admin-form-section-title"><?= e(__('entities.section_contact')) ?></div>
        <?php
        $entityContactCompact = true;
        require __DIR__ . '/_entity_contact_fields.php';
        ?>
    </div>

    <div class="admin-form-section entity-side-panel<?= ($isEdit || $isSystemAdmin) ? '' : ' admin-form-section-last' ?> mb-0">
        <div class="admin-form-section-title"><?= e(__('entities.scope')) ?></div>
        <?php if ($canManageAllEntities): ?>
            <label class="form-label" for="<?= e($efId('entity_department')) ?>"><?= e(__('entities.visibility')) ?></label>
            <select name="department_id" id="<?= e($efId('entity_department')) ?>" class="form-select">
                <option value="0" <?= ($values['department_id'] ?? '') === '' || (string) ($values['department_id'] ?? '') === '0' ? 'selected' : '' ?>><?= e(__('entities.scope_public')) ?></option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= (int) $department['id'] ?>" <?= (string) ($values['department_id'] ?? '') === (string) $department['id'] ? 'selected' : '' ?>>
                        <?= e(__('entities.scope_department') . ': ' . department_display_name($department)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <div class="small text-muted"><i class="bi bi-building me-1"></i><?= e(__('entities.scope_department') . ': ' . ($user['department_name'] ?? __('common.none'))) ?></div>
        <?php endif; ?>
        <?php if ($isEdit): ?>
        <div class="form-check form-switch mt-3 mb-0">
            <input class="form-check-input" type="checkbox" name="is_active" id="<?= e($efId('is_active')) ?>" <?= (int) ($values['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= e($efId('is_active')) ?>"><?= e(__('common.active')) ?></label>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($isSystemAdmin): ?>
    <div class="admin-form-section entity-side-panel admin-form-section-last mb-0">
        <div class="admin-form-section-title"><?= e(__('entities.sort')) ?></div>
        <input type="number" name="sort_order" id="<?= e($efId('entity_sort_order')) ?>" class="form-control" min="0" step="1"
               value="<?= e((string) ($values['sort_order'] ?? '0')) ?>" aria-label="<?= e(__('entities.sort')) ?>">
        <p class="admin-form-hint mb-0 mt-1"><?= e(__('entities.sort_hint')) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!$entityFormInModal && !$entityFormHideActions): ?>
    <div class="entity-form-compact-actions admin-form-actions">
        <button class="btn btn-kff" type="submit" <?= $entityScopeReady ? '' : 'disabled' ?>>
            <i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?>
        </button>
        <a href="<?= e(base_url('admin/entities.php')) ?>" class="btn btn-outline-secondary"><?= e(__('action.cancel')) ?></a>
    </div>
    <?php endif; ?>
</div>
<?php else:
$entityLetterSectionLast = false;
$entityLetterHideTitle = true;
$entityLetterIncludeJob = false;
$entityLetterWide = true;
require __DIR__ . '/_entity_letter_fields.php';
?>
<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('entities.section_basic')) ?></div>
    <div class="mb-3">
        <label class="form-label" for="<?= e($efId('entity_name_ar')) ?>"><?= e(__('entities.name_ar')) ?></label>
        <input type="text" name="name_ar" id="<?= e($efId('entity_name_ar')) ?>" class="form-control" value="<?= e((string) ($values['name_ar'] ?? '')) ?>">
    </div>
    <div class="mb-0">
        <label class="form-label" for="<?= e($efId('entity_job_title')) ?>"><?= e(__('entities.job_title')) ?></label>
        <input type="text" name="job_title" id="<?= e($efId('entity_job_title')) ?>" class="form-control"
               value="<?= e((string) ($values['job_title'] ?? '')) ?>" placeholder="<?= e(__('entities.job_title_ph')) ?>">
    </div>
</div>

<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('entities.section_contact')) ?></div>
    <?php
    $entityContactCompact = false;
    $entityContactShowHint = true;
    require __DIR__ . '/_entity_contact_fields.php';
    ?>
</div>

<div class="admin-form-section<?= ($isEdit || $isSystemAdmin) ? '' : ' admin-form-section-last' ?>">
    <div class="admin-form-section-title"><?= e(__('entities.scope')) ?></div>
    <?php if ($canManageAllEntities): ?>
        <label class="form-label" for="<?= e($efId('entity_department')) ?>"><?= e(__('entities.visibility')) ?></label>
        <select name="department_id" id="<?= e($efId('entity_department')) ?>" class="form-select">
            <option value="0" <?= ($values['department_id'] ?? '') === '' || (string) ($values['department_id'] ?? '') === '0' ? 'selected' : '' ?>><?= e(__('entities.scope_public')) ?></option>
            <?php foreach ($departments as $department): ?>
                <option value="<?= (int) $department['id'] ?>" <?= (string) ($values['department_id'] ?? '') === (string) $department['id'] ? 'selected' : '' ?>>
                    <?= e(__('entities.scope_department') . ': ' . department_display_name($department)) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="admin-form-hint mb-0 mt-2"><?= e(__('entities.scope_hint')) ?></p>
    <?php else: ?>
        <div class="alert alert-light border mb-0">
            <i class="bi bi-building me-1"></i>
            <?= e(__('entities.scope_department') . ': ' . ($user['department_name'] ?? __('common.none'))) ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($isEdit): ?>
<div class="admin-form-section<?= $isSystemAdmin ? '' : ' admin-form-section-last' ?>">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" name="is_active" id="<?= e($efId('is_active')) ?>" <?= (int) ($values['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
        <label class="form-check-label" for="<?= e($efId('is_active')) ?>"><?= e(__('common.active')) ?></label>
    </div>
</div>
<?php endif; ?>

<?php if ($isSystemAdmin): ?>
<div class="admin-form-section admin-form-section-last">
    <div class="admin-form-section-title"><?= e(__('entities.sort')) ?></div>
    <label class="form-label" for="<?= e($efId('entity_sort_order')) ?>"><?= e(__('entities.sort')) ?></label>
    <input type="number" name="sort_order" id="<?= e($efId('entity_sort_order')) ?>" class="form-control" min="0" step="1"
           value="<?= e((string) ($values['sort_order'] ?? '0')) ?>">
    <p class="admin-form-hint mb-0 mt-1"><?= e(__('entities.sort_hint')) ?></p>
</div>
<?php endif; ?>

<?php if (!$entityFormInModal): ?>
<div class="admin-form-actions">
    <button class="btn btn-kff" type="submit" <?= $entityScopeReady ? '' : 'disabled' ?>>
        <i class="bi bi-check2 me-1"></i><?= e($isEdit ? __('action.save_changes') : __('action.save')) ?>
    </button>
    <a href="<?= e(base_url('admin/entities.php')) ?>" class="btn btn-outline-secondary"><?= e(__('action.cancel')) ?></a>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
(function () {
  var prefix = <?= json_encode($entityFormPrefix) ?>;
  function byId(id) { return document.getElementById(prefix + id); }

  var honorSel = byId('entity_honorific');
  var honorWrap = byId('entityHonorificCustomWrap');
  if (honorSel && honorWrap) {
    honorSel.addEventListener('change', function () {
      honorWrap.classList.toggle('d-none', honorSel.value !== '__custom__');
    });
  }

  function rebuildHonorificOptions(selectEl, options, selectedValue) {
    if (!selectEl) return;
    var customOpt = selectEl.querySelector('option[value="__custom__"]');
    while (selectEl.firstChild) {
      selectEl.removeChild(selectEl.firstChild);
    }
    options.forEach(function (opt) {
      var node = document.createElement('option');
      node.value = opt.value;
      node.textContent = opt.label;
      if (opt.value === selectedValue) {
        node.selected = true;
      }
      selectEl.appendChild(node);
    });
    if (customOpt) {
      selectEl.appendChild(customOpt);
    } else {
      var customNode = document.createElement('option');
      customNode.value = '__custom__';
      customNode.textContent = <?= json_encode(__('entities.honorific_custom')) ?>;
      selectEl.appendChild(customNode);
    }
    if (selectedValue && !options.some(function (opt) { return opt.value === selectedValue; })) {
      selectEl.value = '__custom__';
      var customInput = byId('entity_honorific_custom');
      if (customInput) customInput.value = selectedValue;
      if (honorWrap) honorWrap.classList.remove('d-none');
    } else if (selectedValue) {
      selectEl.value = selectedValue;
      if (honorWrap) honorWrap.classList.toggle('d-none', selectedValue === '__custom__');
    }
  }

  var addToggle = byId('entityHonorificAddToggle');
  var addRow = byId('entityHonorificAddRow');
  var addInput = byId('entityHonorificAddInput');
  var addBtn = byId('entityHonorificAddBtn');
  var addError = byId('entityHonorificAddError');
  var csrfInput = document.querySelector('input[name="csrf_token"]');

  if (addToggle && addRow) {
    addToggle.addEventListener('click', function () {
      addRow.classList.toggle('d-none');
      if (!addRow.classList.contains('d-none') && addInput) {
        addInput.focus();
      }
    });
  }

  if (addBtn && addInput && honorSel) {
    addBtn.addEventListener('click', function () {
      var value = addInput.value.trim();
      if (addError) {
        addError.classList.add('d-none');
        addError.textContent = '';
      }
      if (value === '') {
        if (addError) {
          addError.textContent = <?= json_encode(__('entities.honorific_add_empty')) ?>;
          addError.classList.remove('d-none');
        }
        return;
      }
      addBtn.disabled = true;
      var body = new URLSearchParams();
      body.set('action', 'add_honorific_preset');
      body.set('value', value);
      if (csrfInput) body.set('csrf_token', csrfInput.value);
      fetch(<?= json_encode(base_url('admin/entities.php')) ?>, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest' },
        body: body.toString()
      }).then(function (res) { return res.json(); }).then(function (data) {
        if (!data || !data.ok) {
          if (addError) {
            addError.textContent = (data && data.message) ? data.message : <?= json_encode(__('entities.honorific_add_invalid')) ?>;
            addError.classList.remove('d-none');
          }
          return;
        }
        rebuildHonorificOptions(honorSel, data.options || [], data.value || value);
        addInput.value = '';
        if (addRow) addRow.classList.add('d-none');
      }).catch(function () {
        if (addError) {
          addError.textContent = <?= json_encode(__('entities.honorific_add_invalid')) ?>;
          addError.classList.remove('d-none');
        }
      }).finally(function () {
        addBtn.disabled = false;
      });
    });
    addInput.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
        addBtn.click();
      }
    });
  }
})();
</script>
