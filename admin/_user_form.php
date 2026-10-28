<?php
/**
 * حقول نموذج المستخدم (إضافة/تعديل)
 * يتطلب: $values, $isEdit, $departments
 */
?>
<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('users.section_basic')) ?></div>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="user_sort_order"><?= e(__('users.sort')) ?></label>
            <input type="number" name="sort_order" id="user_sort_order" class="form-control text-center" min="0" step="1"
                   value="<?= e((string) ($values['sort_order'] ?? '0')) ?>">
            <div class="form-text"><?= e(__('users.sort_hint')) ?></div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="user_title_prefix"><?= e(__('users.title_prefix')) ?></label>
            <input type="text" name="title_prefix" id="user_title_prefix" class="form-control" maxlength="255"
                   value="<?= e((string) ($values['title_prefix'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="user_name_ar"><?= e(__('users.name_ar')) ?> <span class="text-danger">*</span></label>
            <input type="text" name="name_ar" id="user_name_ar" class="form-control" required maxlength="150" value="<?= e((string) ($values['name_ar'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="user_name_en"><?= e(__('users.name_en')) ?> <span class="text-danger">*</span></label>
            <input type="text" name="name_en" id="user_name_en" class="form-control" required maxlength="150" dir="ltr"
                   pattern="[A-Za-z\s\-']+" title="<?= e(__('users.name_en_letters')) ?>"
                   value="<?= e((string) ($values['name_en'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="user_job_title"><?= e(__('users.job_title')) ?></label>
            <input type="text" name="job_title" id="user_job_title" class="form-control" maxlength="150" value="<?= e((string) ($values['job_title'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="user_job_title_en"><?= e(__('users.job_title_en')) ?></label>
            <input type="text" name="job_title_en" id="user_job_title_en" class="form-control" maxlength="150" dir="ltr" value="<?= e((string) ($values['job_title_en'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="user_phone"><?= e(__('common.phone')) ?></label>
            <input type="text" name="phone" id="user_phone" class="form-control" maxlength="50" dir="ltr" inputmode="tel" autocomplete="tel"
                   value="<?= e((string) ($values['phone'] ?? '')) ?>">
        </div>
    </div>
</div>

<div class="admin-form-section">
    <div class="admin-form-section-title"><?= e(__('users.section_login')) ?></div>
    <div class="row g-3 align-items-end">
        <div class="col-md-6">
            <label class="form-label" for="user_email"><?= e(__('users.email_login')) ?> <span class="text-danger">*</span></label>
            <input type="email" name="email" id="user_email" class="form-control" required dir="ltr" maxlength="150" value="<?= e((string) ($values['email'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="user_password">
                <?= e($isEdit ? __('users.password_keep') : __('users.password')) ?>
                <?php if (!$isEdit): ?><span class="text-danger">*</span><?php endif; ?>
            </label>
            <div class="password-field is-empty">
                <input type="password" name="password" id="user_password" class="form-control" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                <button type="button" class="password-field-toggle" data-password-toggle hidden
                        title="<?= e(__('profile.password_show')) ?>" aria-label="<?= e(__('profile.password_show')) ?>">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <?php if ($isEdit): ?>
        <div class="col-12">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (int) ($values['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active"><?= e(__('common.active')) ?></label>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="admin-form-section admin-form-section-last">
    <div class="admin-form-section-title"><?= e(__('users.section_access')) ?></div>
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label" for="user_role"><?= e(__('users.role')) ?></label>
            <select name="role_id" id="user_role" class="form-select" required>
                <?php foreach (dms_roles_for_user_form((int) ($values['current_role_id'] ?? 0)) as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" data-base-role="<?= e((string) $r['base_role']) ?>"
                            title="<?= e((string) ($r['description'] ?? '')) ?>"
                            <?= (int) ($values['role_id'] ?? 0) === (int) $r['id'] ? 'selected' : '' ?>><?= e(dms_role_display_name($r)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="user_notes"><?= e(__('users.notes')) ?></label>
            <textarea name="notes" id="user_notes" class="form-control" rows="1" maxlength="2000"
                      placeholder="<?= e(__('users.notes_ph')) ?>"><?= e((string) ($values['notes'] ?? '')) ?></textarea>
        </div>
    </div>
    <?php
    $selectedDeptIds = array_fill_keys(array_map('intval', $values['department_ids'] ?? []), true);
    $primaryDeptId = (int) ($values['department_id'] ?? 0);
    if ($primaryDeptId <= 0 && $selectedDeptIds) {
        $primaryDeptId = (int) array_key_first($selectedDeptIds);
    }
    $principalOfficeIds = array_fill_keys(array_map('intval', $values['prep_principal_office_ids'] ?? []), true);
    $delegateOfficeIds = array_fill_keys(array_map('intval', $values['prep_delegate_office_ids'] ?? []), true);
    $editingUserId = (int) ($id ?? ($values['id'] ?? 0));
    $principalHolders = [];
    try {
        if (function_exists('ensure_outgoing_prep_authority_schema')) {
            ensure_outgoing_prep_authority_schema();
        }
        $holderRows = db()->query(
            'SELECT d.id AS dept_id, u.id AS user_id, u.name_ar, u.name_en
             FROM `tbldepartments` d
             INNER JOIN `tblusers` u ON u.id = d.principal_user_id'
        )->fetchAll() ?: [];
        foreach ($holderRows as $holderRow) {
            $principalHolders[(int) $holderRow['dept_id']] = [
                'id' => (int) $holderRow['user_id'],
                'name' => function_exists('user_display_name') ? user_display_name($holderRow) : (string) ($holderRow['name_ar'] ?? ''),
            ];
        }
    } catch (Throwable $e) {
        $principalHolders = [];
    }
    ?>
    <div class="mb-0">
        <div class="d-flex flex-nowrap align-items-center gap-2 mb-2 user-dept-pick-line" id="userDeptPickLine">
            <span class="form-label mb-0 text-nowrap"><?= e(__('users.departments')) ?></span>
            <span class="text-muted small mb-0 text-nowrap" id="userDeptEmpty"><?= e(__('users.departments_empty')) ?></span>
            <?php if ($departments): ?>
            <button type="button" class="btn btn-outline-secondary btn-sm ms-auto text-nowrap" id="userDeptPickBtn"
                    data-bs-toggle="modal" data-bs-target="#userDeptPickerModal">
                <i class="bi bi-building me-1"></i><?= e(__('users.departments_pick')) ?>
            </button>
            <?php endif; ?>
        </div>
        <ul class="user-dept-legend">
            <li><strong><?= e(__('users.department_primary')) ?></strong><span><?= e(__('users.legend_primary')) ?></span></li>
            <li><strong><?= e(__('users.dept_col_assign')) ?></strong><span><?= e(__('users.legend_assign')) ?></span></li>
            <li><strong><?= e(__('users.dept_col_draft')) ?></strong><span><?= e(__('users.legend_draft')) ?></span></li>
            <li><strong><?= e(__('users.dept_col_principal')) ?></strong><span><?= e(__('users.legend_principal')) ?></span></li>
        </ul>
        <div id="userDeptChosen" class="user-dept-chips mb-2"></div>
        <?php if ($principalOfficeIds): ?>
            <div class="mb-2">
                <div class="small text-muted mb-1"><?= e(__('users.prep_is_principal')) ?></div>
                <div class="d-flex flex-wrap gap-1">
                    <?php foreach ($departments as $d): ?>
                        <?php if (empty($principalOfficeIds[(int) $d['id']])) {
                            continue;
                        } ?>
                        <a class="badge text-bg-primary text-decoration-none" href="<?= e(base_url('admin/departments.php?edit=' . (int) $d['id'])) ?>">
                            <?= e(department_display_name($d)) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        <input type="hidden" name="primary_department_id" id="userDeptPrimaryId" value="<?= (int) $primaryDeptId ?>">
        <div id="userDeptHiddenFields" aria-hidden="true">
            <?php foreach (array_keys($selectedDeptIds) as $hid): ?>
                <input type="hidden" name="department_ids[]" value="<?= (int) $hid ?>">
            <?php endforeach; ?>
            <?php foreach (array_keys($delegateOfficeIds) as $hid): ?>
                <input type="hidden" name="prep_delegate_office_ids[]" value="<?= (int) $hid ?>">
            <?php endforeach; ?>
            <?php foreach (array_keys($principalOfficeIds) as $hid): ?>
                <input type="hidden" name="principal_office_ids[]" value="<?= (int) $hid ?>">
            <?php endforeach; ?>
        </div>
        <?php if (!$departments): ?>
            <p class="admin-form-hint mb-0"><?= e(__('users.departments_none')) ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="modal fade" id="userDeptPickerModal" tabindex="-1" aria-labelledby="userDeptPickerTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content approver-picker">
            <div class="modal-header">
                <h5 class="modal-title" id="userDeptPickerTitle">
                    <i class="bi bi-building me-1"></i><?= e(__('users.departments_picker_title')) ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.close')) ?>"></button>
            </div>
            <div class="approver-picker-search">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" id="userDeptPickerSearch" autocomplete="off"
                           placeholder="<?= e(__('users.departments_picker_search')) ?>">
                </div>
            </div>
            <div class="modal-body py-2">
                <div class="user-dept-picker" id="userDeptPicker" data-role-select="user_role">
                    <div class="user-dept-picker-head">
                        <span title="<?= e(__('users.department_primary_tip')) ?>"><?= e(__('users.department_primary')) ?></span>
                        <span><?= e(__('users.dept_col_name')) ?></span>
                        <span title="<?= e(__('users.dept_assign_tip')) ?>"><?= e(__('users.dept_col_assign')) ?></span>
                        <span title="<?= e(__('users.dept_draft_tip')) ?>"><?= e(__('users.dept_col_draft')) ?></span>
                        <span title="<?= e(__('users.dept_principal_tip')) ?>"><?= e(__('users.dept_col_principal')) ?></span>
                    </div>
                    <?php foreach ($departments as $d): ?>
                        <?php
                        $did = (int) $d['id'];
                        $dName = department_display_name($d);
                        $isPrin = !empty($principalOfficeIds[$did]);
                        $isDraft = !empty($delegateOfficeIds[$did]);
                        $isAssigned = isset($selectedDeptIds[$did]);
                        ?>
                        <div class="user-dept-row<?= $isAssigned ? ' is-checked' : '' ?><?= $primaryDeptId === $did ? ' is-primary' : '' ?>"
                             data-search="<?= e(mb_strtolower($dName . ' ' . (string) ($d['name_en'] ?? '') . ' ' . (string) ($d['code'] ?? ''))) ?>">
                            <span class="user-dept-primary">
                                <input type="checkbox" value="<?= $did ?>" class="form-check-input user-dept-primary-input"
                                       <?= $primaryDeptId === $did ? 'checked' : '' ?>
                                       title="<?= e(__('users.department_primary_tip')) ?>"
                                       aria-label="<?= e(__('users.department_primary')) ?>">
                            </span>
                            <span class="user-dept-name"><?= e($dName) ?></span>
                            <span class="user-dept-assign">
                                <input type="checkbox" value="<?= $did ?>"
                                       class="form-check-input user-dept-assign-check"
                                       <?= $isAssigned ? 'checked' : '' ?>
                                       title="<?= e(__('users.dept_assign_tip')) ?>"
                                       aria-label="<?= e(__('users.dept_col_assign')) ?>">
                            </span>
                            <span class="user-dept-draft">
                                <input type="checkbox" value="<?= $did ?>"
                                       class="form-check-input user-dept-draft-check"
                                       <?= $isDraft ? 'checked' : '' ?>
                                       <?= $isPrin ? 'disabled' : '' ?>
                                       title="<?= e($isPrin ? __('users.prep_already_principal') : __('users.dept_draft_tip')) ?>"
                                       aria-label="<?= e(__('users.dept_col_draft')) ?>">
                            </span>
                            <span class="user-dept-principal">
                                <?php
                                $holderId = (int) ($principalHolders[$did]['id'] ?? 0);
                                $holderName = $holderId > 0 && $holderId !== $editingUserId
                                    ? (string) ($principalHolders[$did]['name'] ?? '')
                                    : '';
                                ?>
                                <input type="checkbox" value="<?= $did ?>"
                                       class="form-check-input user-dept-principal-check"
                                       <?= $isPrin ? 'checked' : '' ?>
                                       <?= $holderName !== '' ? 'data-other-name="' . e($holderName) . '"' : '' ?>
                                       title="<?= e(__('users.dept_principal_tip')) ?>"
                                       aria-label="<?= e(__('users.dept_col_principal')) ?>">
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="approver-picker-empty text-muted d-none" id="userDeptPickerEmpty">
                    <i class="bi bi-building-x d-block mb-1"></i><?= e(__('users.departments_picker_empty')) ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-kff" data-bs-dismiss="modal"><?= e(__('users.departments_picker_done')) ?></button>
            </div>
        </div>
    </div>
</div>

<div class="admin-form-actions">
    <button class="btn btn-kff" type="submit">
        <i class="bi bi-check2 me-1"></i><?= e($isEdit ? __('action.save_changes') : __('action.add')) ?>
    </button>
    <a href="<?= e(base_url('admin/users.php')) ?>" class="btn btn-outline-secondary"><?= e(__('action.cancel')) ?></a>
</div>
<script>
(function () {
  var box = document.getElementById('userDeptPicker');
  if (!box) return;
  var roleSel = document.getElementById(box.getAttribute('data-role-select') || 'user_role');
  var chosen = document.getElementById('userDeptChosen');
  var empty = document.getElementById('userDeptEmpty');
  var search = document.getElementById('userDeptPickerSearch');
  var pickerEmpty = document.getElementById('userDeptPickerEmpty');
  var modalEl = document.getElementById('userDeptPickerModal');
  var primaryHidden = document.getElementById('userDeptPrimaryId');
  var hiddenFields = document.getElementById('userDeptHiddenFields');
  var assignText = <?= json_encode(__('users.dept_col_assign'), JSON_UNESCAPED_UNICODE) ?>;
  var primaryText = <?= json_encode(__('users.department_primary'), JSON_UNESCAPED_UNICODE) ?>;
  var draftText = <?= json_encode(__('users.dept_col_draft'), JSON_UNESCAPED_UNICODE) ?>;
  var principalText = <?= json_encode(__('users.dept_col_principal'), JSON_UNESCAPED_UNICODE) ?>;
  var principalConfirm = <?= json_encode(__('users.dept_principal_confirm'), JSON_UNESCAPED_UNICODE) ?>;
  var principalConfirmTitle = <?= json_encode(__('users.dept_principal_confirm_title'), JSON_UNESCAPED_UNICODE) ?>;
  var principalTransfer = <?= json_encode(__('users.dept_principal_transfer'), JSON_UNESCAPED_UNICODE) ?>;
  var removeText = <?= json_encode(__('departments.prep_delegates_remove'), JSON_UNESCAPED_UNICODE) ?>;
  var draftConfirm = <?= json_encode(__('users.dept_draft_confirm'), JSON_UNESCAPED_UNICODE) ?>;
  var draftConfirmTitle = <?= json_encode(__('users.dept_draft_confirm_title'), JSON_UNESCAPED_UNICODE) ?>;
  var assignConfirm = <?= json_encode(__('users.dept_assign_confirm'), JSON_UNESCAPED_UNICODE) ?>;
  var assignConfirmTitle = <?= json_encode(__('users.dept_assign_confirm_title'), JSON_UNESCAPED_UNICODE) ?>;
  var primaryConfirm = <?= json_encode(__('users.dept_primary_confirm'), JSON_UNESCAPED_UNICODE) ?>;
  var primaryConfirmTitle = <?= json_encode(__('users.dept_primary_confirm_title'), JSON_UNESCAPED_UNICODE) ?>;
  if (modalEl && modalEl.parentNode !== document.body) {
    document.body.appendChild(modalEl);
  }
  function askConfirm(msg, title) {
    if (window.DmsSwal && typeof window.DmsSwal.confirm === 'function') {
      return window.DmsSwal.confirm(msg, title);
    }
    return Promise.resolve(window.confirm(msg));
  }
  function confirmCheck(t, msg, title, afterOk) {
    t.checked = false;
    refresh();
    var row = t.closest('.user-dept-row');
    var nameEl = row && row.querySelector('.user-dept-name');
    var deptName = nameEl ? nameEl.textContent.trim() : '';
    askConfirm(msg.replace(':dept', deptName), title).then(function (ok) {
      if (ok) {
        t.checked = true;
        if (afterOk) afterOk(t);
      }
      refresh();
    });
  }
  function assignCheck(row) {
    return row ? row.querySelector('.user-dept-assign-check') : null;
  }
  function primaryCheck(row) {
    return row ? row.querySelector('.user-dept-primary-input') : null;
  }
  function isAssigned(row) {
    var el = assignCheck(row);
    return !!(el && el.checked);
  }
  function exclusivePrimary(keep) {
    box.querySelectorAll('.user-dept-primary-input').forEach(function (el) {
      if (el !== keep) el.checked = false;
    });
  }
  var skipAutoPrimary = false;
  function firstAssignedPrimary() {
    var row = null;
    box.querySelectorAll('.user-dept-row').forEach(function (r) {
      if (!row && isAssigned(r)) row = r;
    });
    var el = primaryCheck(row);
    if (el) {
      exclusivePrimary(el);
      el.checked = true;
    }
  }
  function principalBox(row) {
    return row ? row.querySelector('.user-dept-principal-check') : null;
  }
  function isPrincipal(row) {
    var el = principalBox(row);
    return !!(el && el.checked);
  }
  function syncRow(row) {
    var assign = assignCheck(row);
    var primary = primaryCheck(row);
    var draft = row.querySelector('.user-dept-draft-check');
    var principal = principalBox(row);
    if (principal && principal.checked && draft) {
      draft.checked = false;
      draft.disabled = true;
    } else if (draft) {
      draft.disabled = false;
    }
    var assigned = !!(assign && assign.checked);
    var drafted = !!(draft && draft.checked && !draft.disabled);
    var primaryOn = !!(primary && primary.checked);
    row.classList.toggle('is-checked', assigned || drafted || isPrincipal(row) || primaryOn);
    row.classList.toggle('is-primary', primaryOn);
  }
  function syncPrimaryHidden() {
    if (!primaryHidden) return;
    var on = box.querySelector('.user-dept-primary-input:checked');
    primaryHidden.value = on ? on.value : '';
  }
  function addHidden(name, value) {
    if (!hiddenFields) return;
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    hiddenFields.appendChild(input);
  }
  function syncPostFields() {
    if (!hiddenFields) return;
    hiddenFields.textContent = '';
    box.querySelectorAll('.user-dept-row').forEach(function (row) {
      var assign = assignCheck(row);
      var draft = row.querySelector('.user-dept-draft-check');
      var principal = principalBox(row);
      if (assign && assign.checked) addHidden('department_ids[]', assign.value);
      if (draft && draft.checked && !draft.disabled) addHidden('prep_delegate_office_ids[]', draft.value);
      if (principal && principal.checked) addHidden('principal_office_ids[]', principal.value);
    });
  }
  function renderChips() {
    if (!chosen) return;
    chosen.textContent = '';
    var n = 0;
    box.querySelectorAll('.user-dept-row').forEach(function (row) {
      var assigned = isAssigned(row);
      var primary = primaryCheck(row);
      var draft = row.querySelector('.user-dept-draft-check');
      var drafted = !!(draft && draft.checked && !draft.disabled);
      var principalOn = isPrincipal(row);
      var primaryOn = !!(primary && primary.checked);
      if (!assigned && !drafted && !principalOn && !primaryOn) return;
      n++;
      var nameEl = row.querySelector('.user-dept-name');
      var chip = document.createElement('div');
      chip.className = 'user-dept-chip' + (primary && primary.checked ? ' is-primary' : '');
      var title = document.createElement('span');
      title.className = 'user-dept-chip-name';
      title.textContent = nameEl ? nameEl.textContent : '';
      chip.appendChild(title);
      if (primary && primary.checked) {
        var pb = document.createElement('span');
        pb.className = 'badge text-bg-light border user-dept-chip-primary';
        pb.textContent = primaryText;
        chip.appendChild(pb);
      }
      var flags = document.createElement('span');
      flags.className = 'user-dept-chip-flags';
      if (principalOn) {
        var pr = document.createElement('span');
        pr.className = 'badge text-bg-primary';
        pr.textContent = principalText;
        flags.appendChild(pr);
      }
      if (assigned) {
        var ab = document.createElement('span');
        ab.className = 'badge text-bg-light border';
        ab.textContent = assignText;
        flags.appendChild(ab);
      }
      if (drafted) {
        var db = document.createElement('span');
        db.className = 'badge text-bg-warning';
        db.textContent = draftText;
        flags.appendChild(db);
      }
      chip.appendChild(flags);
      var rm = document.createElement('button');
      rm.type = 'button';
      rm.className = 'btn-close btn-close-sm';
      rm.setAttribute('aria-label', removeText);
      rm.title = removeText;
      rm.addEventListener('click', function () {
        var a = assignCheck(row);
        if (a) a.checked = false;
        var d = row.querySelector('.user-dept-draft-check');
        if (d) d.checked = false;
        var p = primaryCheck(row);
        if (p) p.checked = false;
        var pr = principalBox(row);
        if (pr) pr.checked = false;
        refresh();
      });
      chip.appendChild(rm);
      chosen.appendChild(chip);
    });
    if (empty) {
      empty.classList.toggle('d-none', n > 0);
      empty.hidden = n > 0;
    }
    var pickLine = document.getElementById('userDeptPickLine');
    if (pickLine) pickLine.classList.toggle('is-picked', n > 0);
  }
  function refresh() {
    box.querySelectorAll('.user-dept-row').forEach(syncRow);
    if (!skipAutoPrimary && box.querySelector('.user-dept-assign-check:checked') && !box.querySelector('.user-dept-primary-input:checked')) {
      firstAssignedPrimary();
      box.querySelectorAll('.user-dept-row').forEach(syncRow);
    }
    syncPrimaryHidden();
    syncPostFields();
    renderChips();
  }
  function filterList() {
    var q = (search && search.value ? search.value : '').trim().toLowerCase();
    var shown = 0;
    box.querySelectorAll('.user-dept-row').forEach(function (row) {
      var match = q === '' || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
      row.classList.toggle('d-none', !match);
      if (match) shown++;
    });
    if (pickerEmpty) pickerEmpty.classList.toggle('d-none', shown > 0);
  }
  box.addEventListener('change', function (ev) {
    var t = ev.target;
    if (!t) return;
    if (t.classList.contains('user-dept-assign-check')) {
      if (t.checked) {
        confirmCheck(t, assignConfirm, assignConfirmTitle);
      } else {
        refresh();
      }
      return;
    }
    if (t.classList.contains('user-dept-primary-input')) {
      if (t.checked) {
        confirmCheck(t, primaryConfirm, primaryConfirmTitle, function (el) {
          exclusivePrimary(el);
        });
      } else {
        refresh();
      }
      return;
    }
    if (t.classList.contains('user-dept-draft-check') && t.checked) {
      confirmCheck(t, draftConfirm, draftConfirmTitle);
      return;
    }
    if (t.classList.contains('user-dept-principal-check')) {
      if (t.checked) {
        var other = t.getAttribute('data-other-name') || '';
        var msg = other !== '' ? principalTransfer.replace(':user', other) : principalConfirm;
        confirmCheck(t, msg, principalConfirmTitle);
      } else {
        refresh();
      }
      return;
    }
    refresh();
  });
  if (search) {
    search.addEventListener('input', filterList);
  }
  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', function () {
      if (search) search.focus();
    });
  }
  if (roleSel) roleSel.addEventListener('change', refresh);
  var formEl = (primaryHidden && primaryHidden.form)
    || document.getElementById('userAdminForm')
    || document.getElementById('userCreateForm');
  if (formEl) {
    formEl.addEventListener('submit', function () {
      syncPrimaryHidden();
      syncPostFields();
    });
  }
  refresh();
})();
(function () {
  var input = document.getElementById('user_password');
  if (!input) return;
  var wrap = input.closest('.password-field');
  var btn = wrap && wrap.querySelector('[data-password-toggle]');
  if (!wrap || !btn) return;
  var passShow = <?= json_encode(__('profile.password_show'), JSON_UNESCAPED_UNICODE) ?>;
  var passHide = <?= json_encode(__('profile.password_hide'), JSON_UNESCAPED_UNICODE) ?>;
  function setIcon(show) {
    var icon = btn.querySelector('i');
    if (!icon) return;
    icon.classList.toggle('bi-eye', !show);
    icon.classList.toggle('bi-eye-slash', show);
    var label = show ? passHide : passShow;
    btn.setAttribute('title', label);
    btn.setAttribute('aria-label', label);
  }
  function sync() {
    var has = input.value !== '';
    wrap.classList.toggle('is-empty', !has);
    btn.hidden = !has;
    if (!has) {
      input.type = 'password';
      setIcon(false);
    }
  }
  input.addEventListener('input', sync);
  btn.addEventListener('click', function () {
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    setIcon(show);
  });
  sync();
})();
</script>
