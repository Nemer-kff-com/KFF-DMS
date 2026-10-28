<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.users');
ensure_users_profile_schema();
ensure_mail_draft_schema();
ensure_roles_schema();
$pageTitle = __('users.new');
$departments = departments_list(false);

$isEdit = false;
$error = '';
$values = [
    'name_en' => '',
    'name_ar' => '',
    'job_title' => '',
    'job_title_en' => '',
    'title_prefix' => '',
    'notes' => '',
    'email' => '',
    'phone' => '',
    'role' => 'department',
    'role_id' => dms_system_role_id('department'),
    'current_role_id' => 0,
    'department_id' => 0,
    'sort_order' => (string) user_next_sort_order(),
    'department_ids' => [],
    'prep_principal_office_ids' => [],
    'prep_delegate_office_ids' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('danger', __('profile.photo_too_large_server'));
        redirect(base_url('admin/users_create.php'));
    }
    verify_csrf();
    $values['name_en'] = trim((string) ($_POST['name_en'] ?? $_POST['username'] ?? ''));
    $values['name_ar'] = trim((string) ($_POST['name_ar'] ?? $_POST['full_name'] ?? ''));
    $values['job_title'] = mb_substr(trim((string) ($_POST['job_title'] ?? '')), 0, 150);
    $values['job_title_en'] = mb_substr(trim((string) ($_POST['job_title_en'] ?? '')), 0, 150);
    $values['title_prefix'] = mb_substr(trim((string) ($_POST['title_prefix'] ?? '')), 0, 255);
    $values['notes'] = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000);
    $values['email'] = dms_normalize_user_email((string) ($_POST['email'] ?? ''));
    $values['phone'] = mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 50);
    $values['role_id'] = (int) ($_POST['role_id'] ?? 0);
    $roleChoice = dms_role_resolve_choice($values['role_id']);
    $values['role'] = $roleChoice['base_role'] ?? '';
    $deptPick = function_exists('user_departments_from_request')
        ? user_departments_from_request($_POST, $departments)
        : ['ids' => [], 'primary' => (int) ($_POST['department_id'] ?? 0) ?: null];
    $values['department_ids'] = $deptPick['ids'];
    $values['department_id'] = (int) ($deptPick['primary'] ?? 0);
    $values['sort_order'] = (string) max(0, (int) ($_POST['sort_order'] ?? 0));
    $values['prep_delegate_office_ids'] = array_map('intval', (array) ($_POST['prep_delegate_office_ids'] ?? []));
    $values['prep_principal_office_ids'] = array_map('intval', (array) ($_POST['principal_office_ids'] ?? []));
    $password = (string) ($_POST['password'] ?? '');
    $departmentId = $values['department_id'] ?: null;

    if ($roleChoice === null) {
        $error = __('users.role_invalid');
    } else {
        $profileError = dms_validate_user_profile_data($values['name_ar'], $values['name_en'], $values['email']);
        if ($profileError !== null) {
            $error = $profileError;
        } elseif ($password === '') {
            $error = __('users.password_required');
        } elseif (!dms_password_meets_policy($password)) {
            $error = __('auth.password_min');
        } elseif (dms_role_requires_assigned_department($roleChoice)
            && !$deptPick['ids']
            && !$departmentId
            && !array_filter($values['prep_principal_office_ids'])) {
            $error = __('users.departments_required');
        } elseif ($departmentId && !department_exists($departmentId, $departments)) {
            $error = __('users.invalid');
        } elseif (user_sort_order_taken((int) $values['sort_order'])) {
            $error = __('users.sort_duplicate');
        }
    }

    if ($error === '') {
        try {
            db()->prepare(
                "INSERT INTO `tblusers` (name_en, password, name_ar, title_prefix, job_title, job_title_en, notes, email, phone, role, role_id, department_id, is_active, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $values['name_en'],
                password_hash($password, PASSWORD_DEFAULT),
                $values['name_ar'],
                $values['title_prefix'] !== '' ? $values['title_prefix'] : null,
                $values['job_title'] !== '' ? $values['job_title'] : null,
                $values['job_title_en'] !== '' ? $values['job_title_en'] : null,
                $values['notes'] !== '' ? $values['notes'] : null,
                $values['email'],
                $values['phone'] !== '' ? $values['phone'] : null,
                $values['role'],
                $roleChoice['id'],
                $departmentId,
                1,
                (int) $values['sort_order'],
            ]);
            $newId = (int) db()->lastInsertId();
            if (function_exists('save_user_departments')) {
                save_user_departments($newId, $deptPick['ids'], $deptPick['primary']);
            }
            if (function_exists('outgoing_prep_replace_user_delegate_offices')) {
                outgoing_prep_replace_user_delegate_offices(
                    $newId,
                    array_map('intval', (array) ($_POST['prep_delegate_office_ids'] ?? [])),
                    (int) ($user['id'] ?? 0)
                );
            }
            if (function_exists('user_apply_principal_offices')) {
                user_apply_principal_offices($newId, $values['prep_principal_office_ids']);
            }
            $photoErr = (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($photoErr !== UPLOAD_ERR_NO_FILE && function_exists('upload_user_photo')) {
                try {
                    $path = upload_user_photo($newId, $_FILES['photo'] ?? []);
                    db()->prepare('UPDATE `tblusers` SET photo_path = ? WHERE id = ?')->execute([$path, $newId]);
                } catch (Throwable $e) {
                    flash('warning', __('users.created') . ' — ' . $e->getMessage());
                    redirect(base_url('admin/users_edit.php?id=' . $newId));
                }
            }
            $setupWarnings = function_exists('dms_user_setup_warnings')
                ? dms_user_setup_warnings([
                    'id' => $newId,
                    'role' => (string) ($values['role'] ?? ''),
                    'role_id' => (int) ($roleChoice['id'] ?? 0),
                    'department_id' => (int) ($deptPick['primary'] ?? 0),
                    'assign_ids' => $deptPick['ids'] ?? [],
                    'principal_ids' => $values['prep_principal_office_ids'] ?? [],
                ])
                : [];
            if (function_exists('dms_user_flash_saved')) {
                dms_user_flash_saved(__('users.created'), $setupWarnings);
            } else {
                flash('success', __('users.created'));
            }
            redirect(base_url('admin/users.php'));
        } catch (PDOException $e) {
            $error = ((int) ($e->errorInfo[1] ?? 0) === 1062) ? __('profile.email_taken') : __('users.dup');
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('users.new')) ?></h1>
        <p class="page-sub"><?= e(__('users.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/users.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<form method="post" enctype="multipart/form-data" class="admin-form" id="userCreateForm">
    <?= csrf_field() ?>
    <div class="row g-3 profile-page page-narrow">
        <div class="col-12 col-lg-4">
            <div class="card-panel">
                <div class="card-header"><?= e(__('profile.photo')) ?></div>
                <div class="card-body text-center">
                    <img src="" alt="" class="profile-photo mb-3 d-none" id="createPhotoPreview">
                    <div class="profile-photo-fallback mb-3" id="createPhotoFallback"><i class="bi bi-person"></i></div>
                    <div class="fw-bold" id="createPhotoName"><?= e(__('users.new')) ?></div>
                    <div class="text-muted small d-none" id="createPhotoJob"></div>
                    <div class="small text-muted mt-1" dir="ltr" id="createPhotoEmail"></div>
                    <div class="d-flex justify-content-center flex-wrap gap-1 mt-2 mb-3">
                        <span class="badge text-bg-light border" id="createPhotoRole"></span>
                    </div>
                    <hr class="my-3">
                    <?php
                    $dmsFile = [
                        'name' => 'photo',
                        'id' => 'createPhotoInput',
                        'required' => false,
                        'autosubmit' => false,
                        'show_list' => false,
                        'accept' => '.jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif',
                        'hint' => __('users.photo_hint'),
                        'browse' => __('file.choose_one'),
                        'placeholder' => __('file.placeholder_one'),
                    ];
                    require __DIR__ . '/../includes/dms_file_field.php';
                    ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="admin-editor">
                <div class="card-panel admin-form-panel">
                    <div class="card-header">
                        <div class="admin-editor-header">
                            <span class="admin-editor-icon"><i class="bi bi-person-plus"></i></span>
                            <span><?= e(__('users.new')) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
                        <?php endif; ?>
                        <?php require __DIR__ . '/_user_form.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<script>
(function () {
    var form = document.getElementById('userCreateForm');
    var input = document.getElementById('createPhotoInput');
    var preview = document.getElementById('createPhotoPreview');
    var fallback = document.getElementById('createPhotoFallback');
    var nameEl = document.getElementById('createPhotoName');
    var jobEl = document.getElementById('createPhotoJob');
    var emailEl = document.getElementById('createPhotoEmail');
    var roleEl = document.getElementById('createPhotoRole');
    var nameAr = document.getElementById('user_name_ar');
    var nameEn = document.getElementById('user_name_en');
    var job = document.getElementById('user_job_title');
    var email = document.getElementById('user_email');
    var roleSel = document.getElementById('user_role');
    var fallbackName = <?= json_encode(__('users.new'), JSON_UNESCAPED_UNICODE) ?>;
    var maxBytes = <?= (int) dms_document_upload_max_bytes() ?>;
    var sizeMsg = <?= json_encode(__('profile.photo_size'), JSON_UNESCAPED_UNICODE) ?>;
    var typeMsg = <?= json_encode(__('profile.photo_type'), JSON_UNESCAPED_UNICODE) ?>;
    function photoOk(file) {
        if (!file) return true;
        var okType = /^image\/(jpeg|jpg|pjpeg|png|x-png|webp|gif)$/i.test(file.type)
            || /\.(jpe?g|png|webp|gif)$/i.test(file.name || '');
        if (!okType) {
            if (window.Swal) Swal.fire({ icon: 'error', text: typeMsg });
            else alert(typeMsg);
            return false;
        }
        if (file.size > maxBytes) {
            if (window.Swal) Swal.fire({ icon: 'error', text: sizeMsg });
            else alert(sizeMsg);
            return false;
        }
        return true;
    }
    function syncMeta() {
        var n = ((nameAr && nameAr.value) || (nameEn && nameEn.value) || '').trim();
        if (nameEl) nameEl.textContent = n !== '' ? n : fallbackName;
        var j = (job && job.value || '').trim();
        if (jobEl) {
            jobEl.textContent = j;
            jobEl.classList.toggle('d-none', j === '');
        }
        if (emailEl) emailEl.textContent = (email && email.value || '').trim();
        if (roleEl && roleSel) {
            var opt = roleSel.options[roleSel.selectedIndex];
            roleEl.textContent = opt ? opt.text : '';
        }
    }
    function showPreview(file) {
        if (!preview || !fallback) return;
        if (!file) {
            preview.src = '';
            preview.classList.add('d-none');
            fallback.classList.remove('d-none');
            return;
        }
        var url = URL.createObjectURL(file);
        preview.src = url;
        preview.onload = function () { URL.revokeObjectURL(url); };
        preview.classList.remove('d-none');
        fallback.classList.add('d-none');
    }
    [nameAr, nameEn, job, email, roleSel].forEach(function (el) {
        if (!el) return;
        el.addEventListener('input', syncMeta);
        el.addEventListener('change', syncMeta);
    });
    syncMeta();
    if (input) {
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (file && !photoOk(file)) {
                input.value = '';
                showPreview(null);
                return;
            }
            showPreview(file || null);
        });
    }
    if (form) {
        form.addEventListener('submit', function (e) {
            var file = input && input.files && input.files[0];
            if (file && !photoOk(file)) e.preventDefault();
        });
    }
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
