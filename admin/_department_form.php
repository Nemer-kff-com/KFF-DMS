<?php
/**
 * حقول نموذج القسم (إضافة/تعديل)
 * يتطلب: $values, $isEdit
 * اختياري: $isSystemAdmin, $departmentFormInModal, $signingCandidateDepartments
 */
$inPrefix = trim((string) ($values['incoming_prefix'] ?? ''));
$outPrefix = trim((string) ($values['outgoing_prefix'] ?? ''));
$inSample = corr_ref_sample($inPrefix !== '' ? $inPrefix : corr_global_prefix('incoming'));
$outSample = corr_ref_sample($outPrefix !== '' ? $outPrefix : corr_global_prefix('outgoing'));
$departmentFormInModal = !empty($departmentFormInModal);
$isSystemAdmin = isset($isSystemAdmin)
    ? (bool) $isSystemAdmin
    : (function_exists('can_manage_users') && can_manage_users());
?>
<div class="admin-form-section">
    <div class="admin-form-section-title"><i class="bi bi-person me-1"></i><?= e(__('departments.col_officer')) ?></div>
    <p class="admin-form-hint"><?= e(__('departments.principal_hint')) ?></p>
    <?php
    $prepPickUsers = $prepPickUsers ?? (function_exists('outgoing_approval_active_users') ? outgoing_approval_active_users() : []);
    $principalId = (int) ($values['principal_user_id'] ?? 0);
    $delegateIds = array_map('intval', (array) ($values['prep_delegate_ids'] ?? []));
    $principalUser = null;
    foreach ($prepPickUsers as $pu) {
        if ((int) ($pu['id'] ?? 0) === $principalId) {
            $principalUser = $pu;
            break;
        }
    }
    $principalPhoto = ($principalUser && function_exists('user_photo_url')) ? user_photo_url($principalUser) : '';
    $principalPrefix = $principalUser ? trim((string) ($principalUser['title_prefix'] ?? '')) : '';
    $principalName = $principalUser
        ? (function_exists('user_display_name') ? user_display_name($principalUser) : (string) ($principalUser['name_ar'] ?? ''))
        : __('departments.principal_empty');
    $principalJob = $principalUser
        ? (function_exists('user_job_title') ? user_job_title($principalUser) : (string) ($principalUser['job_title'] ?? ''))
        : '';
    $principalRoleLabel = __('departments.principal');
    $principalChangeTip = str_replace(':role', $principalRoleLabel, __('settings.outgoing_approver_change_role'));
    ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
        <label class="form-label mb-0" for="dept_principal_user_id"><?= e(__('departments.principal')) ?></label>
        <button type="button" class="btn btn-kff btn-sm btn-icon" id="deptPrincipalPickBtn"
                data-approver-pick="dept_principal_user_id"
                data-role-label="<?= e(__('departments.principal')) ?>"
                data-picker-title="<?= e(__('departments.principal_picker_title')) ?>"
                data-pick-label="<?= e(__('departments.principal_pick')) ?>"
                data-change-label="<?= e(__('departments.principal_change')) ?>"
                title="<?= e(__('departments.principal_add_tip')) ?>"
                aria-label="<?= e(__('departments.principal_add_tip')) ?>">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
        </button>
        <a class="btn btn-outline-secondary btn-sm<?= $principalId > 0 ? '' : ' d-none' ?>" id="deptPrincipalUserLink"
           data-base="<?= e(base_url('admin/users_edit.php?id=')) ?>"
           href="<?= e(base_url('admin/users_edit.php?id=' . $principalId)) ?>">
            <?= e(__('departments.principal_from_user')) ?>
        </a>
    </div>
    <select name="principal_user_id" id="dept_principal_user_id" class="d-none" data-approver-select aria-hidden="true" tabindex="-1">
        <option value="0"><?= e(__('departments.principal_none')) ?></option>
        <?php foreach ($prepPickUsers as $pu): ?>
            <?php
            $pid = (int) ($pu['id'] ?? 0);
            $pName = function_exists('user_display_name') ? user_display_name($pu) : (string) ($pu['name_ar'] ?? '');
            $pPrefix = trim((string) ($pu['title_prefix'] ?? ''));
            $pJob = function_exists('user_job_title') ? user_job_title($pu) : (string) ($pu['job_title'] ?? '');
            $pPhoto = function_exists('user_photo_url') ? user_photo_url($pu) : '';
            ?>
            <option value="<?= $pid ?>"
                    data-name="<?= e($pName) ?>"
                    data-title-prefix="<?= e($pPrefix) ?>"
                    data-job="<?= e($pJob) ?>"
                    data-job-title="<?= e($pJob) ?>"
                    data-photo="<?= e($pPhoto) ?>"
                    <?= $principalId === $pid ? 'selected' : '' ?>>
                <?= e($pName) ?><?php if (!empty($pu['email'])): ?> — <?= e((string) $pu['email']) ?><?php endif; ?>
            </option>
        <?php endforeach; ?>
    </select>
    <div class="settings-approver-person<?= $principalUser ? '' : ' is-empty' ?> is-pickable" data-approver-person-for="dept_principal_user_id"
         role="button" tabindex="0" data-approver-pick="dept_principal_user_id"
         data-empty-label="<?= e(__('departments.principal_empty')) ?>"
         data-role-label="<?= e($principalRoleLabel) ?>"
         data-picker-title="<?= e(__('departments.principal_picker_title')) ?>"
         title="<?= e($principalChangeTip) ?>">
        <span class="settings-approver-avatar-wrap">
            <?php if ($principalPhoto !== ''): ?>
                <img src="<?= e($principalPhoto) ?>" alt="" class="settings-approver-avatar">
            <?php else: ?>
                <span class="settings-approver-avatar is-fallback"><i class="bi bi-person" aria-hidden="true"></i></span>
            <?php endif; ?>
        </span>
        <div class="settings-approver-person-text">
            <div class="settings-approver-person-prefix<?= $principalPrefix === '' ? ' d-none' : '' ?>"><?= e($principalPrefix) ?></div>
            <div class="settings-approver-person-name"><?= e($principalName) ?></div>
            <div class="settings-approver-person-job<?= $principalJob === '' ? ' d-none' : '' ?>"><?= e($principalJob) ?></div>
        </div>
    </div>
    <div class="form-text mb-3"><?= e(__('departments.principal_select_hint')) ?></div>
    <div class="row g-3<?= $principalId > 0 ? ' d-none' : '' ?>" id="dept_officer_manual">
        <div class="col-12">
            <label class="form-label" for="dept_title_prefix"><?= e(__('departments.title_prefix')) ?></label>
            <input type="text" name="title_prefix" id="dept_title_prefix" class="form-control" value="<?= e((string) ($values['title_prefix'] ?? '')) ?>" placeholder="<?= e(__('departments.title_prefix_ph')) ?>"<?= $principalId > 0 ? ' disabled' : '' ?>>
        </div>
        <div class="col-12" id="dept_officer_name_wrap">
            <label class="form-label" for="dept_directed_from"><?= e(__('departments.directed_from')) ?></label>
            <input type="text" name="directed_from" id="dept_directed_from" class="form-control" value="<?= e((string) ($values['directed_from'] ?? '')) ?>" placeholder="<?= e(__('departments.directed_from_ph')) ?>"<?= $principalId > 0 ? ' disabled' : '' ?>>
            <div class="form-text"><?= e(__('departments.officer_manual_hint')) ?></div>
        </div>
        <div class="col-12">
            <label class="form-label" for="dept_job_title"><?= e(__('departments.job_title')) ?></label>
            <input type="text" name="job_title" id="dept_job_title" class="form-control" value="<?= e((string) ($values['job_title'] ?? '')) ?>" placeholder="<?= e(__('departments.job_title_ph')) ?>"<?= $principalId > 0 ? ' disabled' : '' ?>>
        </div>
    </div>
</div>

<div class="admin-form-section">
    <div class="admin-form-section-title"><i class="bi bi-building me-1"></i><?= e(__('departments.section_basic')) ?></div>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label" for="dept_name"><?= e(__('departments.name')) ?></label>
            <input type="text" name="name" id="dept_name" class="form-control" required value="<?= e((string) ($values['name'] ?? '')) ?>">
        </div>
        <div class="col-12">
            <label class="form-label" for="dept_name_en"><?= e(__('departments.name_en')) ?></label>
            <input type="text" name="name_en" id="dept_name_en" class="form-control" dir="ltr" value="<?= e((string) ($values['name_en'] ?? '')) ?>" placeholder="Department name">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="dept_code"><?= e(__('departments.code')) ?></label>
            <input type="text" name="code" id="dept_code" class="form-control" dir="ltr" value="<?= e((string) ($values['code'] ?? '')) ?>">
        </div>
        <?php if ($isSystemAdmin): ?>
        <div class="col-md-4">
            <label class="form-label" for="dept_sort_order"><?= e(__('departments.sort')) ?></label>
            <input type="number" name="sort_order" id="dept_sort_order" class="form-control" min="0" step="1"
                   value="<?= e((string) ($values['sort_order'] ?? '0')) ?>">
            <div class="form-text"><?= e(__('departments.sort_hint')) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($isEdit): ?>
        <div class="col-md-4">
            <label class="form-label" for="is_active"><?= e(__('common.status')) ?></label>
            <div class="form-check form-switch dept-active-switch">
                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?= (int) ($values['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_active"><?= e(__('common.active')) ?></label>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="admin-form-section">
    <div class="admin-form-section-title"><i class="bi bi-hash me-1"></i><?= e(__('departments.section_numbering')) ?></div>
    <p class="admin-form-hint"><?= e(__('departments.prefix_hint')) ?></p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="dept_outgoing_prefix"><?= e(__('departments.outgoing_prefix')) ?></label>
            <input type="text" name="outgoing_prefix" id="dept_outgoing_prefix" class="form-control" maxlength="20"
                   value="<?= e($outPrefix) ?>"
                   placeholder="<?= e(corr_global_prefix('outgoing')) ?>">
            <div class="form-text"><span class="admin-code" id="dept_outgoing_prefix_sample" dir="ltr"><?= e($outSample) ?></span></div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="dept_incoming_prefix"><?= e(__('departments.incoming_prefix')) ?></label>
            <input type="text" name="incoming_prefix" id="dept_incoming_prefix" class="form-control" maxlength="20"
                   value="<?= e($inPrefix) ?>"
                   placeholder="<?= e(corr_global_prefix('incoming')) ?>">
            <div class="form-text"><span class="admin-code" id="dept_incoming_prefix_sample" dir="ltr"><?= e($inSample) ?></span></div>
        </div>
    </div>
</div>

<div class="admin-form-section">
    <div class="admin-form-section-title"><i class="bi bi-people me-1"></i><?= e(__('departments.prep_delegates')) ?></div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
        <p class="admin-form-hint mb-0"><?= e(__('departments.prep_delegates_hint')) ?></p>
        <button type="button" class="btn btn-kff btn-sm btn-icon" id="prepDelegateAddBtn"
                data-approver-pick="dept_delegate_pick"
                data-role-label="<?= e(__('departments.prep_delegates')) ?>"
                data-picker-title="<?= e(__('departments.prep_delegate_picker_title')) ?>"
                title="<?= e(__('departments.prep_delegates_add_tip')) ?>"
                aria-label="<?= e(__('departments.prep_delegates_add_tip')) ?>">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
        </button>
    </div>
            <select id="dept_delegate_pick" class="d-none" data-approver-select data-picker-mode="add" aria-hidden="true" tabindex="-1">
                <option value="0"><?= e(__('settings.outgoing_approver_none')) ?></option>
                <?php foreach ($prepPickUsers as $pu): ?>
                    <?php
                    $pid = (int) ($pu['id'] ?? 0);
                    $pName = function_exists('user_display_name') ? user_display_name($pu) : (string) ($pu['name_ar'] ?? '');
                    $pJob = function_exists('user_job_title') ? user_job_title($pu) : (string) ($pu['job_title'] ?? '');
                    $pPhoto = function_exists('user_photo_url') ? user_photo_url($pu) : '';
                    ?>
                    <option value="<?= $pid ?>"
                            data-name="<?= e($pName) ?>"
                            data-job="<?= e($pJob) ?>"
                            data-job-title="<?= e($pJob) ?>"
                            data-photo="<?= e($pPhoto) ?>">
                        <?= e($pName) ?><?php if (!empty($pu['email'])): ?> — <?= e((string) $pu['email']) ?><?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="prepDelegatesChosen" class="prep-delegates-chosen"></div>
            <p class="text-muted small mb-0<?= $delegateIds === [] ? '' : ' d-none' ?>" id="prepDelegatesEmpty"><?= e(__('departments.prep_delegates_empty')) ?></p>
            <div id="prepDelegatesInputs">
                <?php foreach ($delegateIds as $did): ?>
                    <?php if ($did > 0 && $did !== $principalId): ?>
                        <input type="hidden" name="prep_delegate_ids[]" value="<?= $did ?>" data-delegate-id="<?= $did ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
</div>

<?php if (!$departmentFormInModal): ?>
<div class="admin-form-actions">
    <button class="btn btn-kff" type="submit">
        <i class="bi bi-check2 me-1"></i><?= e($isEdit ? __('action.save_changes') : __('action.save')) ?>
    </button>
    <a href="<?= e(base_url('admin/departments.php')) ?>" class="btn btn-outline-secondary"><?= e(__('action.cancel')) ?></a>
</div>
<script>
(function () {
    var cb = document.getElementById('is_signing_office');
    var panel = document.getElementById('signingAccessPanel');
    if (!cb || !panel) return;
    cb.addEventListener('change', function () {
        panel.classList.toggle('d-none', !cb.checked);
    });
})();
</script>
<?php endif; ?>
<script>
(function () {
    var sel = document.getElementById('dept_principal_user_id');
    var nameEl = document.getElementById('dept_directed_from');
    var manualEl = document.getElementById('dept_officer_manual');
    var titleEl = document.getElementById('dept_title_prefix');
    var jobEl = document.getElementById('dept_job_title');
    var pickSel = document.getElementById('dept_delegate_pick');
    var chosenEl = document.getElementById('prepDelegatesChosen');
    var inputsEl = document.getElementById('prepDelegatesInputs');
    var emptyEl = document.getElementById('prepDelegatesEmpty');
    var removeText = <?= json_encode(__('departments.prep_delegates_remove'), JSON_UNESCAPED_UNICODE) ?>;

    function principalId() {
        return sel ? Number(sel.value || 0) : 0;
    }
    function optionFor(id) {
        if (!pickSel) return null;
        return pickSel.querySelector('option[value="' + String(id) + '"]');
    }
    function currentIds() {
        if (!inputsEl) return [];
        return Array.prototype.map.call(inputsEl.querySelectorAll('input[data-delegate-id]'), function (inp) {
            return Number(inp.value || 0);
        }).filter(function (id) { return id > 0; });
    }
    function syncExclude() {
        if (!pickSel) return;
        var skip = currentIds();
        var pid = principalId();
        if (pid > 0) skip.push(pid);
        pickSel.setAttribute('data-exclude-ids', skip.join(','));
    }
    function renderChosen() {
        if (!chosenEl || !inputsEl) return;
        chosenEl.textContent = '';
        var pid = principalId();
        currentIds().forEach(function (id) {
            if (id === pid) return;
            var opt = optionFor(id);
            var row = document.createElement('div');
            row.className = 'settings-approver-person prep-delegate-chip';
            row.setAttribute('data-user-id', String(id));
            var wrap = document.createElement('span');
            wrap.className = 'settings-approver-avatar-wrap';
            var photo = opt ? (opt.getAttribute('data-photo') || '') : '';
            if (photo) {
                var img = document.createElement('img');
                img.src = photo;
                img.alt = '';
                img.className = 'settings-approver-avatar';
                wrap.appendChild(img);
            } else {
                var fb = document.createElement('span');
                fb.className = 'settings-approver-avatar is-fallback';
                fb.innerHTML = '<i class="bi bi-person" aria-hidden="true"></i>';
                wrap.appendChild(fb);
            }
            row.appendChild(wrap);
            var text = document.createElement('div');
            text.className = 'settings-approver-person-text';
            var nm = document.createElement('div');
            nm.className = 'settings-approver-person-name';
            nm.textContent = opt ? (opt.getAttribute('data-name') || '') : '';
            text.appendChild(nm);
            var job = opt ? (opt.getAttribute('data-job-title') || opt.getAttribute('data-job') || '') : '';
            if (job) {
                var jb = document.createElement('div');
                jb.className = 'settings-approver-person-job';
                jb.textContent = job;
                text.appendChild(jb);
            }
            row.appendChild(text);
            var actions = document.createElement('div');
            actions.className = 'settings-approver-actions';
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'btn btn-outline-danger btn-sm';
            rm.setAttribute('title', removeText);
            rm.setAttribute('aria-label', removeText);
            rm.innerHTML = '<i class="bi bi-x-lg" aria-hidden="true"></i>';
            rm.addEventListener('click', function () { removeDelegate(id); });
            actions.appendChild(rm);
            row.appendChild(actions);
            chosenEl.appendChild(row);
        });
        var n = chosenEl.children.length;
        if (emptyEl) emptyEl.classList.toggle('d-none', n > 0);
        syncExclude();
    }
    function setDelegates(ids) {
        if (!inputsEl) return;
        var pid = principalId();
        var seen = {};
        inputsEl.textContent = '';
        (ids || []).forEach(function (id) {
            id = Number(id || 0);
            if (id <= 0 || id === pid || seen[id]) return;
            seen[id] = true;
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'prep_delegate_ids[]';
            inp.value = String(id);
            inp.setAttribute('data-delegate-id', String(id));
            inputsEl.appendChild(inp);
        });
        renderChosen();
    }
    function addDelegate(id) {
        id = Number(id || 0);
        if (id <= 0 || id === principalId()) return;
        var ids = currentIds();
        if (ids.indexOf(id) !== -1) return;
        ids.push(id);
        setDelegates(ids);
    }
    function removeDelegate(id) {
        setDelegates(currentIds().filter(function (x) { return x !== Number(id); }));
    }
    window.dmsSetDeptDelegates = setDelegates;

    function paintOfficerCard(pid, opt) {
        var person = sel && sel.parentElement ? sel.parentElement.querySelector('.settings-approver-person') : null;
        if (!person) return;
        var empty = person.getAttribute('data-empty-label') || '';
        var prefix = '';
        var name = '';
        var job = '';
        var photo = '';
        if (pid > 0 && opt) {
            prefix = opt.getAttribute('data-title-prefix') || '';
            name = opt.getAttribute('data-name') || '';
            job = opt.getAttribute('data-job-title') || opt.getAttribute('data-job') || '';
            photo = opt.getAttribute('data-photo') || '';
        } else {
            prefix = titleEl ? titleEl.value.trim() : '';
            name = nameEl ? nameEl.value.trim() : '';
            job = jobEl ? jobEl.value.trim() : '';
        }
        if (name === '' && prefix === '' && job === '') {
            name = empty;
        }
        person.classList.toggle('is-empty', pid <= 0 && name === empty);
        var prefixEl = person.querySelector('.settings-approver-person-prefix');
        if (prefixEl) {
            prefixEl.textContent = prefix;
            prefixEl.classList.toggle('d-none', prefix === '');
        }
        var cardName = person.querySelector('.settings-approver-person-name');
        if (cardName) cardName.textContent = name;
        var cardJob = person.querySelector('.settings-approver-person-job');
        if (cardJob) {
            cardJob.textContent = job;
            cardJob.classList.toggle('d-none', job === '');
        }
        var wrap = person.querySelector('.settings-approver-avatar-wrap');
        if (wrap) {
            wrap.textContent = '';
            if (photo) {
                var img = document.createElement('img');
                img.src = photo;
                img.alt = '';
                img.className = 'settings-approver-avatar';
                wrap.appendChild(img);
            } else {
                var fb = document.createElement('span');
                fb.className = 'settings-approver-avatar is-fallback';
                fb.innerHTML = '<i class="bi bi-person" aria-hidden="true"></i>';
                wrap.appendChild(fb);
            }
        }
        var link = document.getElementById('deptPrincipalUserLink');
        if (link) {
            var base = link.getAttribute('data-base') || '';
            link.classList.toggle('d-none', pid <= 0);
            if (pid > 0 && base) link.href = base + String(pid);
        }
    }
    function syncPrincipal(fillName) {
        if (!sel) return;
        var opt = sel.options[sel.selectedIndex];
        var pid = Number(sel.value || 0);
        if (pid > 0) {
            setDelegates(currentIds().filter(function (id) { return id !== pid; }));
        } else {
            renderChosen();
        }
        if (manualEl) {
            manualEl.classList.toggle('d-none', pid > 0);
            manualEl.querySelectorAll('input').forEach(function (inp) { inp.disabled = pid > 0; });
        }
        paintOfficerCard(pid, opt);
        if (pid > 0 && opt && fillName) {
            if (nameEl) nameEl.value = opt.getAttribute('data-name') || '';
            if (titleEl) titleEl.value = opt.getAttribute('data-title-prefix') || '';
            if (jobEl) jobEl.value = opt.getAttribute('data-job-title') || opt.getAttribute('data-job') || '';
        }
    }
    if (sel && !sel.dataset.boundPrincipal) {
        sel.dataset.boundPrincipal = '1';
        sel.addEventListener('change', function () {
            var fillName = sel.dataset.skipNameReset !== '1';
            delete sel.dataset.skipNameReset;
            delete sel.dataset.skipJobReset;
            syncPrincipal(fillName);
        });
    }
    if (pickSel && !pickSel.dataset.boundAdd) {
        pickSel.dataset.boundAdd = '1';
        pickSel.addEventListener('change', function () {
            var id = Number(pickSel.value || 0);
            if (id > 0) addDelegate(id);
            pickSel.value = '0';
        });
    }
    window.dmsSyncDeptPrincipal = syncPrincipal;
    renderChosen();
    if (sel) syncPrincipal(false);
})();
</script>
<?php require __DIR__ . '/../includes/dms_user_picker.php'; ?>
