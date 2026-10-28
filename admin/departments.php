<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.departments');
$pageTitle = __('nav.departments');
ensure_departments_schema();

$isSystemAdmin = can_manage_users($user);
$editError = '';
$openEditId = (int) ($_GET['edit'] ?? 0);

$editValues = [
    'id' => 0,
    'name' => '',
    'name_en' => '',
    'code' => '',
    'sort_order' => '0',
    'incoming_prefix' => '',
    'outgoing_prefix' => '',
    'directed_from' => '',
    'title_prefix' => '',
    'job_title' => '',
    'principal_user_id' => 0,
    'prep_delegate_ids' => [],
    'signature_path' => '',
    'is_active' => 1,
    'is_signing_office' => 0,
    'signing_access_ids' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        try {
            db()->prepare('DELETE FROM `tbldepartments` WHERE id=?')->execute([$id]);
            flash('success', __('departments.deleted'));
        } catch (PDOException $e) {
            flash('danger', __('departments.cannot_delete'));
        }
        redirect(base_url('admin/departments.php'));
    }

    if ($action === 'remove_signature' && $id) {
        $department = admin_row('tbldepartments', $id);
        if (!$department) {
            flash('danger', __('common.not_found'));
            redirect(base_url('admin/departments.php'));
        }
        try {
            clear_department_signature($id, (string) ($department['signature_path'] ?? ''));
            flash('success', __('departments.signature_removed'));
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
        }
        redirect(base_url('admin/departments.php?edit=' . $id));
    }

    if ($action === 'update' && $id) {
        $department = admin_row('tbldepartments', $id);
        if (!$department) {
            flash('danger', __('common.not_found'));
            redirect(base_url('admin/departments.php'));
        }

        $editValues = [
            'id' => $id,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'name_en' => trim((string) ($_POST['name_en'] ?? '')),
            'code' => trim((string) ($_POST['code'] ?? '')),
            'incoming_prefix' => corr_sanitize_prefix(trim((string) ($_POST['incoming_prefix'] ?? ''))),
            'outgoing_prefix' => corr_sanitize_prefix(trim((string) ($_POST['outgoing_prefix'] ?? ''))),
            'title_prefix' => trim((string) ($_POST['title_prefix'] ?? '')),
            'directed_from' => trim((string) ($_POST['directed_from'] ?? '')),
            'job_title' => trim((string) ($_POST['job_title'] ?? '')),
            'principal_user_id' => (int) ($_POST['principal_user_id'] ?? 0),
            'prep_delegate_ids' => array_values(array_filter(array_map('intval', (array) ($_POST['prep_delegate_ids'] ?? [])))),
            'signature_path' => (string) ($department['signature_path'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_signing_office' => (int) ($department['is_signing_office'] ?? 0),
            'signing_access_ids' => function_exists('department_signing_allowed_preparing_ids')
                ? department_signing_allowed_preparing_ids($id)
                : [],
            'sort_order' => (string) ($isSystemAdmin
                ? (int) ($_POST['sort_order'] ?? ($department['sort_order'] ?? 0))
                : (int) ($department['sort_order'] ?? 0)),
        ];
        $sortOrder = (int) $editValues['sort_order'];
        $openEditId = $id;

        if ($editValues['name'] === '') {
            $editError = __('common.name_required');
        } elseif (admin_name_exists('tbldepartments', 'name', $editValues['name'], $id)) {
            $editError = __('common.duplicate_name');
        } elseif ($isSystemAdmin && $sortOrder !== (int) ($department['sort_order'] ?? 0) && department_sort_order_taken($sortOrder, $id)) {
            $editError = __('departments.sort_duplicate');
        }

        if ($editError === '') {
            try {
                ensure_departments_signing_office_schema();
                if (function_exists('ensure_outgoing_prep_authority_schema')) {
                    ensure_outgoing_prep_authority_schema();
                }
                $officerName = (string) ($editValues['directed_from'] ?? '');
                if ((int) ($editValues['principal_user_id'] ?? 0) > 0
                    && (int) ($editValues['principal_user_id'] ?? 0) !== (int) ($department['principal_user_id'] ?? 0)) {
                    $officerName = '';
                }
                $printFields = function_exists('outgoing_prep_principal_print_fields')
                    ? outgoing_prep_principal_print_fields(
                        (int) ($editValues['principal_user_id'] ?? 0),
                        (string) ($editValues['job_title'] ?? ''),
                        $officerName,
                        (string) ($editValues['title_prefix'] ?? '')
                    )
                    : [
                        'principal_user_id' => (int) ($editValues['principal_user_id'] ?? 0),
                        'directed_from' => $editValues['directed_from'] !== '' ? $editValues['directed_from'] : null,
                        'job_title' => $editValues['job_title'] !== '' ? $editValues['job_title'] : null,
                    ];
                $editValues['principal_user_id'] = (int) ($printFields['principal_user_id'] ?? 0);
                $editValues['title_prefix'] = (string) ($printFields['title_prefix'] ?? '');
                $editValues['directed_from'] = (string) ($printFields['directed_from'] ?? '');
                $editValues['job_title'] = (string) ($printFields['job_title'] ?? '');
                db()->prepare(
                    'UPDATE `tbldepartments`
                     SET name=?, name_en=?, code=?, sort_order=?, incoming_prefix=?, outgoing_prefix=?,
                         title_prefix=?, directed_from=?, job_title=?, principal_user_id=?, is_active=?, is_signing_office=?
                     WHERE id=?'
                )->execute([
                    $editValues['name'],
                    $editValues['name_en'] !== '' ? $editValues['name_en'] : null,
                    $editValues['code'] !== '' ? $editValues['code'] : null,
                    $sortOrder,
                    $editValues['incoming_prefix'] !== '' ? $editValues['incoming_prefix'] : null,
                    $editValues['outgoing_prefix'] !== '' ? $editValues['outgoing_prefix'] : null,
                    $editValues['title_prefix'] !== '' ? mb_substr($editValues['title_prefix'], 0, 255) : null,
                    $editValues['directed_from'] !== '' ? $editValues['directed_from'] : null,
                    $editValues['job_title'] !== '' ? $editValues['job_title'] : null,
                    $editValues['principal_user_id'] > 0 ? $editValues['principal_user_id'] : null,
                    $editValues['is_active'],
                    $editValues['is_signing_office'],
                    $id,
                ]);
                if (function_exists('outgoing_prep_save_delegates')) {
                    outgoing_prep_save_delegates($id, $editValues['prep_delegate_ids'], (int) ($user['id'] ?? 0));
                }
                flash('success', __('departments.updated'));
                redirect(base_url('admin/departments.php'));
            } catch (Throwable $e) {
                $editError = $e->getMessage();
            }
        }
    } elseif ($action !== '') {
        redirect(base_url('admin/departments.php'));
    }
}

if (function_exists('departments_apply_missing_principals')) {
    departments_apply_missing_principals();
}
$departments = departments_list(false);
$signingCandidateDepartments = $departments;
$prepPickUsers = function_exists('outgoing_approval_active_users') ? outgoing_approval_active_users() : [];
$officerUsers = [];
foreach ($prepPickUsers as $pu) {
    $officerUsers[(int) ($pu['id'] ?? 0)] = $pu;
}

if ($openEditId > 0 && $editError === '') {
    $department = admin_row('tbldepartments', $openEditId);
    if ($department) {
        $editValues = [
            'id' => $openEditId,
            'name' => (string) ($department['name'] ?? ''),
            'name_en' => (string) ($department['name_en'] ?? ''),
            'code' => (string) ($department['code'] ?? ''),
            'sort_order' => (string) (int) ($department['sort_order'] ?? 0),
            'incoming_prefix' => (string) ($department['incoming_prefix'] ?? ''),
            'outgoing_prefix' => (string) ($department['outgoing_prefix'] ?? ''),
            'directed_from' => (string) ($department['directed_from'] ?? ''),
            'title_prefix' => (string) ($department['title_prefix'] ?? ''),
            'job_title' => (string) ($department['job_title'] ?? ''),
            'principal_user_id' => (int) ($department['principal_user_id'] ?? 0),
            'prep_delegate_ids' => function_exists('outgoing_prep_delegate_ids') ? outgoing_prep_delegate_ids($openEditId) : [],
            'signature_path' => (string) ($department['signature_path'] ?? ''),
            'is_active' => (int) ($department['is_active'] ?? 1),
            'is_signing_office' => (int) ($department['is_signing_office'] ?? 0),
            'signing_access_ids' => department_signing_allowed_preparing_ids($openEditId),
        ];
    } else {
        $openEditId = 0;
    }
}

$deptPayloads = [];
foreach ($departments as $d) {
    $did = (int) ($d['id'] ?? 0);
    $deptPayloads[$did] = [
        'id' => $did,
        'name' => (string) ($d['name'] ?? ''),
        'name_en' => (string) ($d['name_en'] ?? ''),
        'code' => (string) ($d['code'] ?? ''),
        'sort_order' => (int) ($d['sort_order'] ?? 0),
        'incoming_prefix' => (string) ($d['incoming_prefix'] ?? ''),
        'outgoing_prefix' => (string) ($d['outgoing_prefix'] ?? ''),
        'directed_from' => (string) ($d['directed_from'] ?? ''),
        'title_prefix' => (string) ($d['title_prefix'] ?? ''),
        'job_title' => (string) ($d['job_title'] ?? ''),
        'principal_user_id' => (int) ($d['principal_user_id'] ?? 0),
        'prep_delegate_ids' => function_exists('outgoing_prep_delegate_ids') ? outgoing_prep_delegate_ids($did) : [],
        'signature_path' => (string) ($d['signature_path'] ?? ''),
        'signature_url' => !empty($d['signature_path']) ? base_url((string) $d['signature_path']) : '',
        'is_active' => (int) ($d['is_active'] ?? 1),
        'is_signing_office' => (int) ($d['is_signing_office'] ?? 0),
        'signing_access_ids' => department_signing_allowed_preparing_ids($did),
    ];
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('departments.title')) ?></h1>
        <p class="page-sub"><?= e(__('departments.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <span class="admin-count-badge"><i class="bi bi-building me-1"></i><?= count($departments) ?></span>
        <a class="btn btn-outline-secondary btn-sm"
           href="<?= e(base_url('admin/departments_print.php')) ?>"
           target="_blank"
           rel="noopener"
           title="<?= e(__('action.print')) ?>">
            <i class="bi bi-printer"></i>
        </a>
        <?= btn_icon_add(base_url('admin/departments_create.php'), __('departments.new')) ?>
    </div>
</div>

<div class="card-panel page-narrow">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-ul me-1"></i><?= e(__('departments.title')) ?></span>
        <span class="badge text-bg-light text-dark"><?= count($departments) ?></span>
    </div>
    <?php if (!$departments): ?>
        <div class="admin-empty">
            <i class="bi bi-building"></i>
            <p><?= e(__('common.no_data')) ?></p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table admin-table departments-table mb-0 align-middle">
            <thead>
                <tr>
                    <th class="departments-col-num">#</th>
                    <?php if ($isSystemAdmin): ?>
                    <th class="text-center departments-col-sort"><?= e(__('departments.sort')) ?></th>
                    <?php endif; ?>
                    <th><?= e(__('departments.col_name')) ?></th>
                    <th><?= e(__('departments.col_officer')) ?></th>
                    <th><?= e(__('common.code')) ?></th>
                    <th><?= e(__('departments.section_numbering')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                    <th class="text-end"><?= e(__('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($departments as $i => $d): ?>
                <tr>
                    <td class="departments-col-num text-center"><?= (int) ($i + 1) ?></td>
                    <?php if ($isSystemAdmin): ?>
                    <td class="text-center departments-col-sort"><?= (int) ($d['sort_order'] ?? 0) ?></td>
                    <?php endif; ?>
                    <td>
                        <div class="admin-entity-name"><?= e($d['name']) ?></div>
                        <?php if (!empty($d['name_en'])): ?>
                            <div class="admin-entity-sub" dir="ltr"><?= e($d['name_en']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $officer = $officerUsers[(int) ($d['principal_user_id'] ?? 0)] ?? null;
                        if ($officer) {
                            $officerTitle = trim((string) ($d['title_prefix'] ?? ''));
                            if ($officerTitle === '') {
                                $officerTitle = trim((string) ($officer['title_prefix'] ?? ''));
                            }
                            $officerName = function_exists('user_display_name') ? user_display_name($officer) : trim((string) ($officer['name_ar'] ?? ''));
                            $officerJob = trim((string) ($d['job_title'] ?? ''));
                            if ($officerJob === '' && function_exists('user_job_title')) {
                                $officerJob = user_job_title($officer);
                            }
                        } else {
                            $officerTitle = trim((string) ($d['title_prefix'] ?? ''));
                            $officerName = trim((string) ($d['directed_from'] ?? ''));
                            $officerJob = trim((string) ($d['job_title'] ?? ''));
                        }
                        ?>
                        <?php if ($officerTitle !== '' || $officerName !== '' || $officerJob !== '' || !empty($d['principal_user_id'])): ?>
                            <?php if ($officerTitle !== ''): ?>
                                <div class="admin-entity-sub"><?= e($officerTitle) ?></div>
                            <?php endif; ?>
                            <div class="admin-entity-name"><?= e($officerName !== '' ? $officerName : __('common.none')) ?></div>
                            <?php if ($officerJob !== ''): ?>
                                <div class="admin-entity-sub"><?= e($officerJob) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($d['is_signing_office']) && empty($d['principal_user_id'])): ?>
                                <div class="text-warning small mt-1" title="<?= e(__('departments.principal_missing')) ?>">
                                    <i class="bi bi-exclamation-triangle me-1"></i><?= e(__('departments.principal_missing_short')) ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if (!empty($d['is_signing_office'])): ?>
                                <span class="text-warning small" title="<?= e(__('departments.principal_missing')) ?>">
                                    <i class="bi bi-exclamation-triangle me-1"></i><?= e(__('departments.principal_missing_short')) ?>
                                </span>
                            <?php else: ?>
                            <span class="text-muted"><?= e(__('common.none')) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($d['code'])): ?>
                            <span class="admin-code"><?= e($d['code']) ?></span>
                        <?php else: ?>
                            <span class="text-muted"><?= e(__('common.none')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $inP = trim((string) ($d['incoming_prefix'] ?? ''));
                        $outP = trim((string) ($d['outgoing_prefix'] ?? ''));
                        ?>
                        <?php if ($inP !== '' || $outP !== ''): ?>
                            <?php if ($outP !== ''): ?>
                                <div><span class="text-muted small"><?= e(__('type.outgoing')) ?></span> <span class="admin-code"><?= e($outP) ?></span></div>
                            <?php endif; ?>
                            <?php if ($inP !== ''): ?>
                                <div><span class="text-muted small"><?= e(__('type.incoming')) ?></span> <span class="admin-code"><?= e($inP) ?></span></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted"><?= e(__('departments.prefix_default')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= (int) $d['is_active']
                            ? '<span class="badge text-bg-success">' . e(__('common.active')) . '</span>'
                            : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="admin-actions">
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary btn-dept-edit"
                                    title="<?= e(__('action.edit')) ?>"
                                    data-bs-toggle="modal"
                                    data-bs-target="#departmentEditModal"
                                    data-dept-id="<?= (int) $d['id'] ?>"
                                    data-dept="<?= e(json_encode($deptPayloads[(int) $d['id']], JSON_UNESCAPED_UNICODE)) ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" class="d-inline" data-confirm="<?= e(__('departments.confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="<?= e(__('action.delete')) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="departmentEditModal" tabindex="-1" aria-labelledby="departmentEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered department-edit-modal-dialog">
        <div class="modal-content">
            <form method="post" class="admin-form department-edit-form" id="departmentEditForm" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="dept_edit_action" value="update">
                <input type="hidden" name="id" id="dept_edit_id" value="<?= (int) ($editValues['id'] ?? 0) ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="departmentEditModalLabel">
                        <i class="bi bi-pencil-square me-1"></i>
                        <span id="departmentEditTitle"><?= e(__('departments.edit')) ?></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.close')) ?>"></button>
                </div>
                <div class="modal-body department-edit-modal-body">
                    <?php if ($editError !== ''): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-1"></i><?= e($editError) ?>
                        </div>
                    <?php endif; ?>
                    <?php
                    $isEdit = true;
                    $values = $editValues;
                    $departmentFormInModal = true;
                    require __DIR__ . '/_department_form.php';
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('action.cancel')) ?></button>
                    <button type="submit" class="btn btn-kff" id="dept_edit_save_btn">
                        <i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var payloads = <?= json_encode($deptPayloads, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT) ?>;
    var modalEl = document.getElementById('departmentEditModal');
    var form = document.getElementById('departmentEditForm');
    var openEditId = <?= (int) $openEditId ?>;
    var openEditPayload = <?= json_encode(
        $openEditId > 0
            ? [
                'id' => (int) ($editValues['id'] ?? 0),
                'name' => (string) ($editValues['name'] ?? ''),
                'name_en' => (string) ($editValues['name_en'] ?? ''),
                'code' => (string) ($editValues['code'] ?? ''),
                'sort_order' => (int) ($editValues['sort_order'] ?? 0),
                'incoming_prefix' => (string) ($editValues['incoming_prefix'] ?? ''),
                'outgoing_prefix' => (string) ($editValues['outgoing_prefix'] ?? ''),
                'directed_from' => (string) ($editValues['directed_from'] ?? ''),
                'title_prefix' => (string) ($editValues['title_prefix'] ?? ''),
                'job_title' => (string) ($editValues['job_title'] ?? ''),
                'principal_user_id' => (int) ($editValues['principal_user_id'] ?? 0),
                'prep_delegate_ids' => array_values(array_map('intval', (array) ($editValues['prep_delegate_ids'] ?? []))),
                'signature_path' => (string) ($editValues['signature_path'] ?? ''),
                'signature_url' => !empty($editValues['signature_path']) ? base_url((string) $editValues['signature_path']) : '',
                'is_active' => (int) ($editValues['is_active'] ?? 1),
                'is_signing_office' => (int) ($editValues['is_signing_office'] ?? 0),
                'signing_access_ids' => array_values(array_map('intval', (array) ($editValues['signing_access_ids'] ?? []))),
            ]
            : null,
        JSON_UNESCAPED_UNICODE
    ) ?>;
    var defaultIncomingPrefix = <?= json_encode(corr_global_prefix('incoming'), JSON_UNESCAPED_UNICODE) ?>;
    var defaultOutgoingPrefix = <?= json_encode(corr_global_prefix('outgoing'), JSON_UNESCAPED_UNICODE) ?>;
    var numberingYearYy = <?= json_encode(corr_year_yy(), JSON_UNESCAPED_UNICODE) ?>;
    var editTitleBase = <?= json_encode(__('departments.edit'), JSON_UNESCAPED_UNICODE) ?>;

    function setVal(id, value) {
        var el = document.getElementById(id);
        if (el) {
            el.value = value == null ? '' : String(value);
        }
    }

    function setChecked(id, on) {
        var el = document.getElementById(id);
        if (el) {
            el.checked = !!on;
        }
    }

    function updatePrefixSamples() {
        var year = numberingYearYy || '';
        var inEl = document.getElementById('dept_incoming_prefix');
        var outEl = document.getElementById('dept_outgoing_prefix');
        var inSample = document.getElementById('dept_incoming_prefix_sample');
        var outSample = document.getElementById('dept_outgoing_prefix_sample');
        var inP = ((inEl && inEl.value) || '').trim() || defaultIncomingPrefix;
        var outP = ((outEl && outEl.value) || '').trim() || defaultOutgoingPrefix;
        function sample(p) {
            if (year === '') return p ? (p + '/0001') : '0001';
            return p ? (p + '/' + year + '/0001') : (year + '/0001');
        }
        if (inSample) inSample.textContent = sample(inP);
        if (outSample) outSample.textContent = sample(outP);
    }

    function setSigningPanel(visible) {
        var panel = document.getElementById('signingAccessPanel');
        if (panel) {
            panel.classList.toggle('d-none', !visible);
        }
    }

    function parseDeptData(raw) {
        if (!raw) {
            return null;
        }
        if (typeof raw === 'object') {
            return raw;
        }
        try {
            return JSON.parse(String(raw));
        } catch (err) {
            return null;
        }
    }

    function fillDepartment(data) {
        if (!data) {
            return;
        }
        setVal('dept_edit_id', data.id);
        setVal('dept_edit_action', 'update');
        setVal('dept_name', data.name);
        setVal('dept_name_en', data.name_en);
        setVal('dept_code', data.code);
        setVal('dept_sort_order', data.sort_order);
        setVal('dept_incoming_prefix', data.incoming_prefix);
        setVal('dept_outgoing_prefix', data.outgoing_prefix);
        setVal('dept_title_prefix', data.title_prefix);
        setVal('dept_directed_from', data.directed_from);
        setVal('dept_job_title', data.job_title);
        setVal('dept_principal_user_id', data.principal_user_id || 0);
        var jobEl = document.getElementById('dept_job_title');
        if (jobEl) {
            if (data.job_title) jobEl.dataset.touched = '1';
            else delete jobEl.dataset.touched;
        }
        if (typeof window.dmsSetDeptDelegates === 'function') {
            window.dmsSetDeptDelegates(data.prep_delegate_ids || []);
        }
        var principalSel = document.getElementById('dept_principal_user_id');
        if (principalSel) {
            principalSel.dataset.skipJobReset = '1';
            principalSel.dataset.skipNameReset = '1';
            principalSel.dispatchEvent(new Event('change', { bubbles: true }));
        } else if (typeof window.dmsSyncDeptPrincipal === 'function') {
            window.dmsSyncDeptPrincipal();
        }
        setChecked('is_active', Number(data.is_active) === 1);
        setChecked('is_signing_office', Number(data.is_signing_office) === 1);
        setSigningPanel(Number(data.is_signing_office) === 1);
        updatePrefixSamples();

        var access = {};
        (data.signing_access_ids || []).forEach(function (id) {
            access[Number(id)] = true;
        });
        document.querySelectorAll('.signing-access-item').forEach(function (row) {
            var oid = Number(row.getAttribute('data-dept-id') || 0);
            row.classList.toggle('d-none', oid === Number(data.id));
            var cb = row.querySelector('input[type="checkbox"]');
            if (cb) {
                cb.checked = !!access[oid];
                cb.disabled = oid === Number(data.id);
            }
        });

        var stage = document.getElementById('dept_signature_stage');
        var img = document.getElementById('dept_signature_img');
        var empty = document.getElementById('dept_signature_empty');
        var removeBtn = document.getElementById('dept_remove_signature_btn');
        var fileInp = document.getElementById('dept_signature');
        if (fileInp) {
            fileInp.value = '';
        }
        if (data.signature_url) {
            if (img) {
                img.src = data.signature_url;
                img.classList.remove('d-none');
            }
            if (empty) {
                empty.classList.add('d-none');
            }
            if (stage) {
                stage.classList.remove('is-empty');
            }
            if (removeBtn) {
                removeBtn.classList.remove('d-none');
            }
        } else {
            if (img) {
                img.removeAttribute('src');
                img.classList.add('d-none');
            }
            if (empty) {
                empty.classList.remove('d-none');
            }
            if (stage) {
                stage.classList.add('is-empty');
            }
            if (removeBtn) {
                removeBtn.classList.add('d-none');
            }
        }

        var title = document.getElementById('departmentEditTitle');
        if (title) {
            title.textContent = data.name ? (editTitleBase + ' — ' + data.name) : editTitleBase;
        }

        var body = document.querySelector('#departmentEditModal .department-edit-modal-body');
        if (body) {
            body.scrollTop = 0;
        }
    }

    var signingOffice = document.getElementById('is_signing_office');
    if (signingOffice) {
        signingOffice.addEventListener('change', function () {
            setSigningPanel(!!signingOffice.checked);
        });
    }
    ['dept_incoming_prefix', 'dept_outgoing_prefix'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', updatePrefixSamples);
        }
    });

    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function (event) {
            var btn = event.relatedTarget;
            var data = null;
            if (btn && btn.classList.contains('btn-dept-edit')) {
                data = parseDeptData(btn.getAttribute('data-dept'));
                if (!data) {
                    var id = Number(btn.getAttribute('data-dept-id') || 0);
                    data = payloads[id] || payloads[String(id)] || null;
                }
            }
            if (data) {
                fillDepartment(data);
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function () {
            var act = document.getElementById('dept_edit_action');
            if (act && document.activeElement && document.activeElement.getAttribute('value') === 'remove_signature') {
                act.value = 'remove_signature';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (openEditId > 0 && openEditPayload && window.bootstrap && modalEl) {
            fillDepartment(openEditPayload);
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
