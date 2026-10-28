<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.users');
ensure_users_profile_schema();
ensure_mail_draft_schema();
ensure_roles_schema();
$pageTitle = __('users.edit');
$departments = departments_list(false);

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$target = $id ? admin_row('tblusers', $id) : null;
if (!$target) {
    flash('danger', __('users.not_found'));
    redirect(base_url('admin/users.php'));
}

$isEdit = true;
$error = '';
$values = [
    'name_en' => (string) ($target['name_en'] ?? $target['username'] ?? ''),
    'name_ar' => (string) ($target['name_ar'] ?? $target['full_name'] ?? ''),
    'job_title' => (string) ($target['job_title'] ?? ''),
    'job_title_en' => (string) ($target['job_title_en'] ?? ''),
    'title_prefix' => (string) ($target['title_prefix'] ?? ''),
    'notes' => (string) ($target['notes'] ?? ''),
    'email' => (string) ($target['email'] ?? ''),
    'phone' => (string) ($target['phone'] ?? ''),
    'role' => (string) ($target['role'] ?? 'department'),
    'role_id' => user_role_id($target),
    'current_role_id' => user_role_id($target),
    'department_id' => (int) ($target['department_id'] ?? 0),
    'sort_order' => (string) (int) ($target['sort_order'] ?? 0),
    'department_ids' => function_exists('user_departments_load') ? user_departments_load($id) : [],
    'prep_principal_office_ids' => function_exists('outgoing_prep_principal_office_ids') ? outgoing_prep_principal_office_ids($id) : [],
    'prep_delegate_office_ids' => function_exists('outgoing_prep_user_delegate_office_ids') ? outgoing_prep_user_delegate_office_ids($id) : [],
    'is_active' => (int) ($target['is_active'] ?? 1),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // عند تجاوز post_max_size تصبح POST/FILES فارغة قبل CSRF — أظهر رسالة حجم واضحة
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('danger', __('profile.photo_too_large_server'));
        redirect(base_url('admin/users_edit.php?id=' . $id));
    }
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'upload_photo') {
        try {
            $path = upload_user_photo($id, $_FILES['photo'] ?? [], (string) ($target['photo_path'] ?? ''));
            db()->prepare('UPDATE `tblusers` SET photo_path = ? WHERE id = ?')->execute([$path, $id]);
            log_activity((int) $user['id'], null, 'profile_photo', 'رفع صورة مستخدم #' . $id . ' من الإدارة');
            flash('success', __('profile.photo_uploaded'));
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
        }
        redirect(base_url('admin/users_edit.php?id=' . $id));
    }

    if ($action === 'upload_signature') {
        try {
            ensure_users_profile_schema();
            $oldSign = (string) ($target['signature_path'] ?? '');
            $path = upload_user_signature($_FILES['signature'] ?? [], $id, $oldSign);
            db()->prepare('UPDATE `tblusers` SET signature_path = ? WHERE id = ?')->execute([$path, $id]);
            if ($oldSign !== '' && $oldSign !== $path) {
                department_signature_unlink_if_unused($oldSign);
            }
            flash('success', __('users.signature_uploaded'));
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
        }
        redirect(base_url('admin/users_edit.php?id=' . $id));
    }

    if ($action === 'remove_signature') {
        try {
            clear_user_signature($id, (string) ($target['signature_path'] ?? ''));
            flash('success', __('users.signature_removed'));
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
        }
        redirect(base_url('admin/users_edit.php?id=' . $id));
    }

    if ($action === 'remove_photo') {
        try {
            remove_user_photo_file((string) ($target['photo_path'] ?? ''));
            db()->prepare('UPDATE `tblusers` SET photo_path = NULL WHERE id = ?')->execute([$id]);
            log_activity((int) $user['id'], null, 'profile_photo_remove', 'حذف صورة مستخدم #' . $id . ' من الإدارة');
            flash('success', __('profile.photo_removed'));
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
        }
        redirect(base_url('admin/users_edit.php?id=' . $id));
    }

    $values['name_en'] = trim((string) ($_POST['name_en'] ?? $_POST['username'] ?? ''));
    $values['name_ar'] = trim((string) ($_POST['name_ar'] ?? $_POST['full_name'] ?? ''));
    $values['job_title'] = mb_substr(trim((string) ($_POST['job_title'] ?? '')), 0, 150);
    $values['job_title_en'] = mb_substr(trim((string) ($_POST['job_title_en'] ?? '')), 0, 150);
    $values['title_prefix'] = mb_substr(trim((string) ($_POST['title_prefix'] ?? '')), 0, 255);
    $values['notes'] = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000);
    $values['email'] = dms_normalize_user_email((string) ($_POST['email'] ?? ''));
    $values['phone'] = mb_substr(trim((string) ($_POST['phone'] ?? '')), 0, 50);
    $values['role_id'] = (int) ($_POST['role_id'] ?? 0);
    $roleChoice = dms_role_resolve_choice($values['role_id'], (int) $values['current_role_id']);
    $values['role'] = $roleChoice['base_role'] ?? '';
    $deptPick = function_exists('user_departments_from_request')
        ? user_departments_from_request($_POST, $departments)
        : ['ids' => [], 'primary' => (int) ($_POST['department_id'] ?? 0) ?: null];
    $values['department_ids'] = $deptPick['ids'];
    $values['department_id'] = (int) ($deptPick['primary'] ?? 0);
    $values['sort_order'] = (string) max(0, (int) ($_POST['sort_order'] ?? 0));
    $values['prep_delegate_office_ids'] = array_map('intval', (array) ($_POST['prep_delegate_office_ids'] ?? []));
    $values['prep_principal_office_ids'] = array_map('intval', (array) ($_POST['principal_office_ids'] ?? []));
    $values['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $password = (string) ($_POST['password'] ?? '');
    $departmentId = $values['department_id'] ?: null;

    if ($roleChoice === null) {
        $error = __('users.role_invalid');
    } else {
        $profileError = dms_validate_user_profile_data($values['name_ar'], $values['name_en'], $values['email'], $id);
        if ($profileError !== null) {
            $error = $profileError;
        } elseif (dms_role_requires_assigned_department($roleChoice)
            && !$deptPick['ids']
            && !$departmentId
            && !array_filter($values['prep_principal_office_ids'])) {
            $error = __('users.departments_required');
        } elseif ($departmentId && !department_exists($departmentId, $departments)) {
            $error = __('users.invalid');
        } elseif (user_sort_order_taken((int) $values['sort_order'], $id)) {
            $error = __('users.sort_duplicate');
        } elseif ($password !== '' && !dms_password_meets_policy($password)) {
            $error = __('auth.password_min');
        } elseif ($id === (int) $user['id'] && ($values['role'] !== 'admin' || $values['is_active'] !== 1)) {
            $error = __('users.cannot_demote_self');
        }
    }

    if ($error === '') {
        try {
            if ($password !== '') {
                db()->prepare(
                    "UPDATE `tblusers` SET name_en=?, password=?, name_ar=?, title_prefix=?, job_title=?, job_title_en=?, notes=?, email=?, phone=?, role=?, department_id=?, is_active=?, sort_order=? WHERE id=?"
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
                    $departmentId,
                    $values['is_active'],
                    (int) $values['sort_order'],
                    $id,
                ]);
                if (function_exists('dms_invalidate_user_sessions')) {
                    dms_invalidate_user_sessions($id);
                }
            } else {
                db()->prepare(
                    "UPDATE `tblusers` SET name_en=?, name_ar=?, title_prefix=?, job_title=?, job_title_en=?, notes=?, email=?, phone=?, role=?, department_id=?, is_active=?, sort_order=? WHERE id=?"
                )->execute([
                    $values['name_en'],
                    $values['name_ar'],
                    $values['title_prefix'] !== '' ? $values['title_prefix'] : null,
                    $values['job_title'] !== '' ? $values['job_title'] : null,
                    $values['job_title_en'] !== '' ? $values['job_title_en'] : null,
                    $values['notes'] !== '' ? $values['notes'] : null,
                    $values['email'],
                    $values['phone'] !== '' ? $values['phone'] : null,
                    $values['role'],
                    $departmentId,
                    $values['is_active'],
                    (int) $values['sort_order'],
                    $id,
                ]);
            }
            if (function_exists('save_user_departments')) {
                save_user_departments($id, $deptPick['ids'], $deptPick['primary']);
            }
            if (function_exists('outgoing_prep_replace_user_delegate_offices')) {
                outgoing_prep_replace_user_delegate_offices(
                    $id,
                    array_map('intval', (array) ($_POST['prep_delegate_office_ids'] ?? [])),
                    (int) ($user['id'] ?? 0)
                );
            }
            if (function_exists('user_apply_principal_offices')) {
                user_apply_principal_offices($id, $values['prep_principal_office_ids']);
            }
            if (function_exists('outgoing_prep_sync_principal_display_name')) {
                outgoing_prep_sync_principal_display_name($id);
            }
            db()->prepare('UPDATE `tblusers` SET role_id = ? WHERE id = ?')->execute([$roleChoice['id'], $id]);
            $setupWarnings = function_exists('dms_user_setup_warnings')
                ? dms_user_setup_warnings([
                    'id' => $id,
                    'role' => (string) ($values['role'] ?? ''),
                    'role_id' => (int) ($roleChoice['id'] ?? 0),
                    'department_id' => (int) ($deptPick['primary'] ?? 0),
                    'assign_ids' => $deptPick['ids'] ?? [],
                    'principal_ids' => $values['prep_principal_office_ids'] ?? [],
                ])
                : [];
            if (function_exists('dms_user_flash_saved')) {
                dms_user_flash_saved(__('users.updated'), $setupWarnings);
            } else {
                flash('success', __('users.updated'));
            }
            redirect(base_url('admin/users_edit.php?id=' . $id));
        } catch (PDOException $e) {
            $error = ((int) ($e->errorInfo[1] ?? 0) === 1062) ? __('profile.email_taken') : __('users.dup');
        }
    }
}

$target = admin_row('tblusers', $id) ?: $target;
$photoUrl = function_exists('user_photo_url') ? user_photo_url($target) : '';
$signaturePath = trim((string) ($target['signature_path'] ?? ''));
$signatureUrl = $signaturePath !== '' ? base_url($signaturePath) : '';

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('users.edit')) ?></h1>
        <p class="page-sub"><?= e(user_display_name($target)) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-primary" href="<?= e(base_url('admin/user_permissions.php?id=' . (int) $id)) ?>"
           title="<?= e(__('perm.user_btn_tip')) ?>" data-bs-toggle="tooltip">
            <i class="bi bi-shield-check me-1"></i><?= e(__('perm.user_title')) ?>
            <?php $ovCount = dms_user_permission_override_count($target); ?>
            <?php if ($ovCount > 0): ?><span class="badge text-bg-warning ms-1"><?= $ovCount ?></span><?php endif; ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/users.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="row g-3 profile-page page-narrow user-edit-page">
    <div class="col-12 col-lg-4">
        <div class="card-panel">
            <div class="card-header"><?= e(__('profile.photo')) ?></div>
            <div class="card-body text-center">
                <?php if ($photoUrl !== ''): ?>
                    <img src="<?= e($photoUrl) ?>" alt="" class="profile-photo mb-3">
                <?php else: ?>
                    <div class="profile-photo-fallback mb-3"><i class="bi bi-person"></i></div>
                <?php endif; ?>
                <?php $targetJob = function_exists('user_job_title') ? user_job_title($target) : ''; ?>
                <?php $targetPrefix = trim((string) ($target['title_prefix'] ?? '')); ?>
                <?php if ($targetPrefix !== ''): ?>
                    <div class="text-muted small"><?= e($targetPrefix) ?></div>
                <?php endif; ?>
                <div class="fw-bold"><?= e(user_display_name($target)) ?></div>
                <?php if ($targetJob !== ''): ?>
                    <div class="text-muted small"><?= e($targetJob) ?></div>
                <?php endif; ?>
                <div class="small text-muted mt-1" dir="ltr"><?= e((string) ($target['email'] ?? '')) ?></div>
                <?php $targetPhone = trim((string) ($target['phone'] ?? '')); ?>
                <?php if ($targetPhone !== ''): ?>
                    <div class="small text-muted" dir="ltr"><?= e($targetPhone) ?></div>
                <?php endif; ?>
                <div class="d-flex justify-content-center flex-wrap gap-1 mt-2 mb-3">
                    <span class="badge text-bg-light border"><?= e(user_role_label($target)) ?></span>
                    <?php if ((int) ($target['is_active'] ?? 1) === 1): ?>
                        <span class="badge text-bg-success"><?= e(__('common.active')) ?></span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary"><?= e(__('common.inactive')) ?></span>
                    <?php endif; ?>
                </div>
                <hr class="my-3">

                <form method="post" enctype="multipart/form-data" class="text-start" id="adminUploadPhotoForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="action" value="upload_photo">
                    <?php
                    $dmsFile = [
                        'name' => 'photo',
                        'id' => 'adminPhotoInput',
                        'required' => false,
                        'autosubmit' => true,
                        'show_list' => false,
                        'accept' => '.jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif',
                        'hint' => __('users.photo_hint'),
                        'browse' => __('file.choose_one'),
                        'placeholder' => __('file.placeholder_one'),
                    ];
                    require __DIR__ . '/../includes/dms_file_field.php';
                    ?>
                </form>
                <script>
                (function () {
                    var form = document.getElementById('adminUploadPhotoForm');
                    var input = document.getElementById('adminPhotoInput');
                    if (!form || !input) return;
                    var maxBytes = <?= (int) dms_document_upload_max_bytes() ?>;
                    var sizeMsg = <?= json_encode(__('profile.photo_size'), JSON_UNESCAPED_UNICODE) ?>;
                    var typeMsg = <?= json_encode(__('profile.photo_type'), JSON_UNESCAPED_UNICODE) ?>;
                    form.addEventListener('submit', function (e) {
                        var file = input.files && input.files[0];
                        if (!file) {
                            e.preventDefault();
                            return;
                        }
                        var okType = /^image\/(jpeg|jpg|pjpeg|png|x-png|webp|gif)$/i.test(file.type)
                            || /\.(jpe?g|png|webp|gif)$/i.test(file.name || '');
                        if (!okType) {
                            e.preventDefault();
                            if (window.Swal) Swal.fire({ icon: 'error', text: typeMsg });
                            else alert(typeMsg);
                            return;
                        }
                        if (file.size > maxBytes) {
                            e.preventDefault();
                            if (window.Swal) Swal.fire({ icon: 'error', text: sizeMsg });
                            else alert(sizeMsg);
                        }
                    });
                })();
                </script>

                <?php if ($photoUrl !== ''): ?>
                    <form method="post" class="mt-2" id="adminRemovePhotoForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                        <input type="hidden" name="action" value="remove_photo">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100" id="adminRemovePhotoBtn">
                            <i class="bi bi-trash"></i> <?= e(__('profile.remove_photo')) ?>
                        </button>
                    </form>
                    <script>
                    document.getElementById('adminRemovePhotoBtn')?.addEventListener('click', function () {
                        var form = document.getElementById('adminRemovePhotoForm');
                        var msg = <?= json_encode(__('profile.confirm_remove_photo'), JSON_UNESCAPED_UNICODE) ?>;
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'warning',
                                title: <?= json_encode(__('common.confirm') ?: 'تأكيد', JSON_UNESCAPED_UNICODE) ?>,
                                text: msg,
                                showCancelButton: true,
                                confirmButtonColor: '#dc3545',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: <?= json_encode(__('action.delete') ?: 'حذف', JSON_UNESCAPED_UNICODE) ?>,
                                cancelButtonText: <?= json_encode(__('action.cancel') ?: 'إلغاء', JSON_UNESCAPED_UNICODE) ?>
                            }).then(function (r) { if (r.isConfirmed) form.submit(); });
                        } else if (window.confirm(msg)) {
                            form.submit();
                        }
                    });
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-panel mt-3">
            <div class="card-header"><?= e(__('users.signature_section')) ?></div>
            <div class="card-body text-center">
                <div class="user-signature-stage <?= $signatureUrl !== '' ? '' : 'is-empty' ?> mb-3">
                    <?php if ($signatureUrl !== ''): ?>
                        <img src="<?= e($signatureUrl) ?>" alt="<?= e(__('users.signature_section')) ?>">
                    <?php else: ?>
                        <div class="settings-logo-empty">
                            <i class="bi bi-pen"></i>
                            <span><?= e(__('users.no_signature')) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <form method="post" enctype="multipart/form-data" class="text-start" id="adminUploadSignatureForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="action" value="upload_signature">
                    <?php
                    $dmsFile = [
                        'name' => 'signature',
                        'id' => 'adminSignatureInput',
                        'required' => false,
                        'autosubmit' => true,
                        'show_list' => false,
                        'accept' => '.jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif',
                        'hint' => __('users.signature_hint'),
                        'browse' => __('file.choose_one'),
                        'placeholder' => __('file.placeholder_one'),
                    ];
                    require __DIR__ . '/../includes/dms_file_field.php';
                    ?>
                </form>
                <?php if ($signatureUrl !== ''): ?>
                    <form method="post" class="mt-2" id="adminRemoveSignatureForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                        <input type="hidden" name="action" value="remove_signature">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100" id="adminRemoveSignatureBtn">
                            <i class="bi bi-trash"></i> <?= e(__('users.remove_signature')) ?>
                        </button>
                    </form>
                    <script>
                    document.getElementById('adminRemoveSignatureBtn')?.addEventListener('click', function () {
                        var form = document.getElementById('adminRemoveSignatureForm');
                        var msg = <?= json_encode(__('users.confirm_remove_signature'), JSON_UNESCAPED_UNICODE) ?>;
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'warning',
                                text: msg,
                                showCancelButton: true,
                                confirmButtonColor: '#dc3545',
                                cancelButtonColor: '#6c757d',
                                confirmButtonText: <?= json_encode(__('action.delete'), JSON_UNESCAPED_UNICODE) ?>,
                                cancelButtonText: <?= json_encode(__('action.cancel'), JSON_UNESCAPED_UNICODE) ?>
                            }).then(function (r) { if (r.isConfirmed) form.submit(); });
                        } else if (window.confirm(msg)) {
                            form.submit();
                        }
                    });
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="admin-editor">
            <div class="card-panel admin-form-panel is-editing">
                <div class="card-header">
                    <div class="admin-editor-header">
                        <span class="admin-editor-icon"><i class="bi bi-person-gear"></i></span>
                        <span><?= e(__('users.edit')) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post" class="admin-form" id="userAdminForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                        <input type="hidden" name="action" value="save">
                        <?php require __DIR__ . '/_user_form.php'; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
