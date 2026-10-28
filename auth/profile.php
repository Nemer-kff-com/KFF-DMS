<?php
/**
 * الملف الشخصي — تحديث البيانات، الصورة، وكلمة المرور
 */
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
ensure_users_profile_schema();
$pageTitle = __('nav.profile');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('danger', __('profile.photo_too_large_server'));
        redirect(base_url('auth/profile.php'));
    }
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $uid = (int) $user['id'];

    try {
        if ($action === 'update_profile') {
            $nameAr = trim((string) ($_POST['name_ar'] ?? $_POST['full_name'] ?? ''));
            $nameEn = trim((string) ($_POST['name_en'] ?? $_POST['username'] ?? ''));
            $email = dms_normalize_user_email((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['phone'] ?? ''));

            $profileError = dms_validate_user_profile_data($nameAr, $nameEn, $email, $uid);
            if ($profileError !== null) {
                throw new InvalidArgumentException($profileError);
            }

            try {
                db()->prepare(
                    'UPDATE `tblusers` SET name_ar = ?, name_en = ?, email = ?, phone = ? WHERE id = ?'
                )->execute([$nameAr, $nameEn, $email, $phone !== '' ? $phone : null, $uid]);
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    throw new InvalidArgumentException(__('profile.email_taken'));
                }
                throw $e;
            }

            try {
                $sid = session_id();
                if ($sid !== '') {
                    db()->prepare('UPDATE `tbluser_sessions` SET user_name = ? WHERE session_id = ?')
                        ->execute([user_actor_name([
                            'name_ar' => $nameAr,
                            'name_en' => $nameEn,
                            'email' => $email,
                        ]), $sid]);
                }
            } catch (Throwable $e) {
                // اختياري
            }

            log_activity($uid, null, 'profile_update', 'تحديث الملف الشخصي');
            log_security('PROFILE_UPDATE', 'INFO', 'user=' . $email, $uid);
            if (function_exists('outgoing_prep_sync_principal_display_name')) {
                outgoing_prep_sync_principal_display_name($uid);
            }
            flash('success', __('profile.updated'));
            redirect(base_url('auth/profile.php'));
        }

        if ($action === 'upload_photo') {
            $path = upload_user_photo($uid, $_FILES['photo'] ?? [], (string) ($user['photo_path'] ?? ''));
            db()->prepare('UPDATE `tblusers` SET photo_path = ? WHERE id = ?')->execute([$path, $uid]);
            log_activity($uid, null, 'profile_photo', 'رفع صورة شخصية');
            flash('success', __('profile.photo_uploaded'));
            redirect(base_url('auth/profile.php'));
        }

        if ($action === 'remove_photo') {
            remove_user_photo_file((string) ($user['photo_path'] ?? ''));
            db()->prepare('UPDATE `tblusers` SET photo_path = NULL WHERE id = ?')->execute([$uid]);
            log_activity($uid, null, 'profile_photo_remove', 'حذف صورة شخصية');
            flash('success', __('profile.photo_removed'));
            redirect(base_url('auth/profile.php'));
        }

        if ($action === 'coverage_grant' && function_exists('user_coverage_create')) {
            user_coverage_create(
                $uid,
                (int) ($_POST['grantee_user_id'] ?? 0),
                trim((string) ($_POST['starts_on'] ?? '')),
                trim((string) ($_POST['ends_on'] ?? '')),
                array_map('intval', (array) ($_POST['coverage_assign'] ?? [])),
                array_map('intval', (array) ($_POST['coverage_draft'] ?? []))
            );
            log_activity($uid, null, 'user_coverage', __('coverage.granted'));
            flash('success', __('coverage.granted'));
            redirect(base_url('auth/profile.php'));
        }

        if ($action === 'coverage_revoke' && function_exists('user_coverage_revoke')) {
            user_coverage_revoke((int) ($_POST['coverage_id'] ?? 0), $uid);
            log_activity($uid, null, 'user_coverage', __('coverage.revoked'));
            flash('success', __('coverage.revoked'));
            redirect(base_url('auth/profile.php'));
        }

        if ($action === 'change_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $newPass = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            if ($current === '' || $newPass === '' || $confirm === '') {
                throw new InvalidArgumentException(__('profile.password_required'));
            }
            if ($newPass !== $confirm) {
                throw new InvalidArgumentException(__('auth.password_mismatch'));
            }
            if (!dms_password_meets_policy($newPass)) {
                throw new InvalidArgumentException(__('auth.password_min'));
            }

            $stmt = db()->prepare('SELECT password FROM `tblusers` WHERE id = ? LIMIT 1');
            $stmt->execute([$uid]);
            $hash = (string) $stmt->fetchColumn();
            if ($hash === '' || !password_verify($current, $hash)) {
                throw new InvalidArgumentException(__('profile.password_wrong'));
            }

            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            db()->prepare('UPDATE `tblusers` SET password = ? WHERE id = ?')->execute([$newHash, $uid]);
            if (function_exists('dms_invalidate_user_sessions')) {
                dms_invalidate_user_sessions($uid);
            }
            log_activity($uid, null, 'password_change', 'تغيير كلمة المرور من الملف الشخصي');
            log_security('PASSWORD_CHANGE', 'INFO', 'self', $uid);
            flash('success', __('profile.password_changed'));
            redirect(base_url('auth/login.php'));
        }

        throw new InvalidArgumentException(__('profile.invalid_action'));
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
        redirect(base_url('auth/profile.php'));
    }
}

// إعادة تحميل بعد التحديث (التخزين المؤقت في current_user)
$user = current_user() ?: $user;
$photoUrl = user_photo_url($user);
$deptLabel = department_display_name($user, 'department_name', 'department_name_en');

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('profile.title')) ?></h1>
        <p class="page-sub"><?= e(__('profile.subtitle')) ?></p>
    </div>
</div>

<div class="row g-3 profile-page page-narrow">
    <div class="col-12 col-lg-4">
        <div class="card card-panel">
            <div class="card-header"><?= e(__('profile.photo')) ?></div>
            <div class="card-body text-center">
                <?php if ($photoUrl !== ''): ?>
                    <img src="<?= e($photoUrl) ?>" alt="" class="profile-photo mb-3">
                <?php else: ?>
                    <div class="profile-photo-fallback mb-3"><i class="bi bi-person"></i></div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" class="text-start" id="profilePhotoForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_photo">
                    <?php
                    $dmsFile = [
                        'name' => 'photo',
                        'id' => 'profilePhoto',
                        'required' => false,
                        'autosubmit' => true,
                        'show_list' => false,
                        'accept' => '.jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif',
                        'hint' => __('profile.photo_hint'),
                        'browse' => __('file.choose_one'),
                        'placeholder' => __('file.placeholder_one'),
                    ];
                    require __DIR__ . '/../includes/dms_file_field.php';
                    ?>
                </form>
                <script>
                (function () {
                    var form = document.getElementById('profilePhotoForm');
                    var input = document.getElementById('profilePhoto');
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
                    <form method="post" class="mt-2" onsubmit="return confirm(<?= json_encode(__('profile.confirm_remove_photo'), JSON_UNESCAPED_UNICODE) ?>);">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_photo">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash"></i> <?= e(__('profile.remove_photo')) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card card-panel mb-3">
            <div class="card-header"><?= e(__('profile.info')) ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= e(__('users.name_ar')) ?></label>
                            <input type="text" name="name_ar" class="form-control" required maxlength="150"
                                   value="<?= e($user['name_ar'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(__('users.name_en')) ?></label>
                            <input type="text" name="name_en" id="profile_name_en" class="form-control" required maxlength="150"
                                   dir="ltr" pattern="[A-Za-z\s\-']+" title="<?= e(__('users.name_en_letters')) ?>"
                                   value="<?= e($user['name_en'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(__('users.email_login')) ?> <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required maxlength="150"
                                   value="<?= e($user['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(__('common.phone')) ?></label>
                            <input type="text" name="phone" class="form-control" maxlength="50"
                                   value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(__('users.role')) ?></label>
                            <input type="text" class="form-control bg-light" value="<?= e(user_role_label($user)) ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= e(__('nav.departments')) ?></label>
                            <input type="text" class="form-control bg-light" value="<?= e($deptLabel !== '' ? $deptLabel : __('common.none')) ?>" readonly>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-kff">
                            <i class="bi bi-check2"></i> <?= e(__('action.save_changes')) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-panel profile-password-panel mb-3">
            <div class="card-header"><?= e(__('profile.password_section')) ?></div>
            <div class="card-body">
                <form method="post" autocomplete="off">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="form-label"><?= e(__('profile.current_password')) ?></label>
                            <div class="password-field">
                                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                                <button type="button" class="password-field-toggle" data-password-toggle title="<?= e(__('profile.password_show')) ?>" aria-label="<?= e(__('profile.password_show')) ?>">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label"><?= e(__('profile.new_password')) ?></label>
                            <div class="password-field">
                                <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
                                <button type="button" class="password-field-toggle" data-password-toggle title="<?= e(__('profile.password_show')) ?>" aria-label="<?= e(__('profile.password_show')) ?>">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            <div class="form-text"><?= e(__('profile.password_min_hint')) ?></div>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label"><?= e(__('profile.confirm_password')) ?></label>
                            <div class="password-field">
                                <input type="password" name="confirm_password" class="form-control" required minlength="6" autocomplete="new-password">
                                <button type="button" class="password-field-toggle" data-password-toggle title="<?= e(__('profile.password_show')) ?>" aria-label="<?= e(__('profile.password_show')) ?>">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-kff">
                            <i class="bi bi-shield-lock"></i> <?= e(__('profile.change_password')) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php
        $covUid = (int) ($user['id'] ?? 0);
        $covCanGrant = $covUid > 0 && function_exists('user_coverage_can_grant') && user_coverage_can_grant($covUid);
        $covAssignIds = $covCanGrant ? array_fill_keys(user_coverage_grantable_assign_ids($covUid), true) : [];
        $covDraftIds = $covCanGrant ? array_fill_keys(user_coverage_grantable_draft_ids($covUid), true) : [];
        $covDeptIds = array_keys($covAssignIds + $covDraftIds);
        $covMine = function_exists('user_coverage_list_for') ? user_coverage_list_for($covUid, 'grantor') : [];
        $covReceived = function_exists('user_coverage_list_for') ? user_coverage_list_for($covUid, 'grantee') : [];
        $covVisible = static fn (array $row): bool => (string) ($row['status'] ?? '') !== 'revoked';
        $covMine = array_values(array_filter($covMine, $covVisible));
        $covReceived = array_values(array_filter($covReceived, $covVisible));
        $covUsers = ($covCanGrant || $covMine || $covReceived) && function_exists('outgoing_approval_active_users')
            ? outgoing_approval_active_users()
            : [];
        $covUsersById = [];
        foreach ($covUsers as $cu) {
            $covUsersById[(int) ($cu['id'] ?? 0)] = $cu;
        }
        $covStatusLabel = static function (string $st): string {
            $key = 'coverage.status_' . $st;
            $t = __($key);
            return $t !== $key ? $t : $st;
        };
        ?>
        <?php if ($covCanGrant || $covMine || $covReceived): ?>
        <div class="card card-panel mb-3">
            <div class="card-header"><?= e(__('coverage.title')) ?></div>
            <div class="card-body">
                <p class="admin-form-hint"><?= nl2br(e(__('coverage.hint')), false) ?></p>
                <?php if ($covCanGrant && $covDeptIds): ?>
                <?php $covPrimaryId = (int) ($covDeptIds[0] ?? 0); $covCanAssign = $covAssignIds !== []; $covCanDraft = $covDraftIds !== []; ?>
                <form method="post" id="coverageGrantForm" class="mb-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="coverage_grant">
                    <input type="hidden" name="grantee_user_id" id="coverage_grantee" value="0">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="coverage_starts"><?= e(__('coverage.starts')) ?></label>
                            <input type="date" name="starts_on" id="coverage_starts" class="form-control" required value="<?= e(date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="coverage_ends"><?= e(__('coverage.ends')) ?></label>
                            <input type="date" name="ends_on" id="coverage_ends" class="form-control" required value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>">
                        </div>
                    </div>
                    <div class="d-flex flex-nowrap align-items-center gap-2 mb-2 coverage-pick-line<?= $covMine ? ' is-picked' : '' ?>" id="coveragePickLine">
                        <span class="form-label mb-0 text-nowrap"><?= e(__('coverage.pick_user')) ?></span>
                        <div id="coverageChosen" class="user-dept-chips mb-0"></div>
                        <?php if (!$covMine): ?>
                            <span class="text-muted small mb-0 text-nowrap" id="coverageChosenEmpty"><?= e(__('coverage.pick_user_empty')) ?></span>
                            <span class="text-muted small mb-0 text-nowrap" id="coverageEmptyMine"><?= e(__('coverage.empty_mine')) ?></span>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm ms-auto text-nowrap" data-bs-toggle="modal" data-bs-target="#coveragePickerModal">
                            <i class="bi bi-person-plus me-1"></i><?= e(__('coverage.pick_user_title')) ?>
                        </button>
                    </div>
                    <div class="modal fade" id="coveragePickerModal" tabindex="-1" aria-labelledby="coveragePickerTitle" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                            <div class="modal-content approver-picker">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="coveragePickerTitle">
                                        <i class="bi bi-person-check me-1"></i><?= e(__('coverage.pick_user_title')) ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('action.close')) ?>"></button>
                                </div>
                                <div class="approver-picker-search">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="search" class="form-control" id="coveragePickerSearch" autocomplete="off"
                                               placeholder="<?= e(__('coverage.picker_search')) ?>">
                                    </div>
                                </div>
                                <div class="modal-body py-2">
                                    <ul class="user-dept-legend">
                                        <li><strong><?= e(__('users.dept_col_assign')) ?></strong><span><?= e(__('coverage.legend_assign')) ?></span></li>
                                        <li><strong><?= e(__('users.dept_col_draft')) ?></strong><span><?= e(__('coverage.legend_draft')) ?></span></li>
                                    </ul>
                                    <div class="user-dept-picker coverage-user-picker" id="coverageUserPicker">
                                        <div class="user-dept-picker-head coverage-user-head">
                                            <span><?= e(__('coverage.user_col')) ?></span>
                                            <span title="<?= e(__('users.dept_assign_tip')) ?>"><?= e(__('users.dept_col_assign')) ?></span>
                                            <span title="<?= e(__('users.dept_draft_tip')) ?>"><?= e(__('users.dept_col_draft')) ?></span>
                                        </div>
                                        <?php foreach ($covUsers as $pu): ?>
                                            <?php
                                            $pid = (int) ($pu['id'] ?? 0);
                                            if ($pid <= 0 || $pid === $covUid || user_coverage_user_is_admin($pu)) {
                                                continue;
                                            }
                                            $pName = user_display_name($pu);
                                            $pPrefix = trim((string) ($pu['title_prefix'] ?? ''));
                                            $pJob = function_exists('user_job_title') ? user_job_title($pu) : '';
                                            $pPhoto = function_exists('user_photo_url') ? user_photo_url($pu) : '';
                                            $pSearch = mb_strtolower($pPrefix . ' ' . $pName . ' ' . $pJob . ' ' . (string) ($pu['email'] ?? ''));
                                            ?>
                                            <div class="user-dept-row coverage-user-row" data-user-id="<?= $pid ?>" data-search="<?= e($pSearch) ?>">
                                                <span class="coverage-user-id">
                                                    <span class="settings-approver-avatar-wrap">
                                                        <?php if ($pPhoto !== ''): ?>
                                                            <img src="<?= e($pPhoto) ?>" alt="" class="settings-approver-avatar">
                                                        <?php else: ?>
                                                            <span class="settings-approver-avatar is-fallback"><i class="bi bi-person" aria-hidden="true"></i></span>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="coverage-user-text">
                                                        <?php if ($pPrefix !== ''): ?>
                                                            <span class="coverage-user-prefix"><?= e($pPrefix) ?></span>
                                                        <?php endif; ?>
                                                        <span class="user-dept-name coverage-user-name"><?= e($pName) ?></span>
                                                    </span>
                                                </span>
                                                <span class="user-dept-assign">
                                                    <?php if ($covCanAssign): ?>
                                                        <input type="checkbox" name="coverage_assign[]" value="<?= $covPrimaryId ?>"
                                                               class="form-check-input coverage-assign-check"
                                                               title="<?= e(__('users.dept_assign_tip')) ?>"
                                                               aria-label="<?= e(__('users.dept_col_assign')) ?>">
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="user-dept-draft">
                                                    <?php if ($covCanDraft): ?>
                                                        <input type="checkbox" name="coverage_draft[]" value="<?= $covPrimaryId ?>"
                                                               class="form-check-input coverage-draft-check"
                                                               title="<?= e(__('users.dept_draft_tip')) ?>"
                                                               aria-label="<?= e(__('users.dept_col_draft')) ?>">
                                                    <?php else: ?>
                                                        <span class="text-muted" title="<?= e(__('users.dept_draft_na_tip')) ?>">—</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="approver-picker-empty text-muted d-none" id="coveragePickerEmpty">
                                        <i class="bi bi-person-x d-block mb-1"></i><?= e(__('coverage.picker_empty')) ?>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-kff" id="coveragePickerSubmit">
                                        <?= e(__('coverage.submit')) ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <?php elseif ($covCanGrant): ?>
                    <p class="text-muted small"><?= e(__('coverage.no_depts')) ?></p>
                <?php endif; ?>

                <?php
                $covRenderList = static function (array $rows, bool $asGrantor) use ($covUsersById, $covStatusLabel): void {
                    $otherKey = $asGrantor ? 'grantee_user_id' : 'grantor_user_id';
                    foreach ($rows as $row):
                        $oid = (int) ($row[$otherKey] ?? 0);
                        $ou = $covUsersById[$oid] ?? null;
                        $st = (string) ($row['status'] ?? '');
                        $canRevoke = $asGrantor && in_array($st, ['active', 'scheduled'], true);
                        ?>
                        <div class="coverage-item coverage-item-<?= e($st) ?>">
                            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                                <div>
                                    <?php $covPrefix = trim((string) ($ou['title_prefix'] ?? '')); ?>
                                    <?php if ($covPrefix !== ''): ?>
                                        <div class="coverage-user-prefix"><?= e($covPrefix) ?></div>
                                    <?php endif; ?>
                                    <div class="fw-semibold"><?= e($ou ? user_display_name($ou) : ('#' . $oid)) ?></div>
                                    <div class="small text-muted"><?= e((string) ($row['starts_on'] ?? '')) ?> — <?= e((string) ($row['ends_on'] ?? '')) ?></div>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <?php
                                        $hasAssign = false;
                                        $hasDraft = false;
                                        foreach ((array) ($row['items'] ?? []) as $it) {
                                            if (!empty($it['grant_assign'])) {
                                                $hasAssign = true;
                                            }
                                            if (!empty($it['grant_draft'])) {
                                                $hasDraft = true;
                                            }
                                        }
                                        ?>
                                        <?php if ($hasAssign): ?>
                                            <span class="badge text-bg-light border"><?= e(__('users.dept_col_assign')) ?></span>
                                        <?php endif; ?>
                                        <?php if ($hasDraft): ?>
                                            <span class="badge text-bg-warning"><?= e(__('users.dept_col_draft')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge <?= $st === 'active' ? 'text-bg-success' : ($st === 'scheduled' ? 'text-bg-info' : 'text-bg-secondary') ?>"><?= e($covStatusLabel($st)) ?></span>
                                    <?php if ($canRevoke): ?>
                                        <form method="post" class="coverage-revoke-form mb-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="coverage_revoke">
                                            <input type="hidden" name="coverage_id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><?= e(__('coverage.revoke')) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach;
                };
                ?>

                <?php if ($covMine): ?>
                    <h6 class="mt-3"><?= e(__('coverage.mine')) ?></h6>
                    <?php $covRenderList($covMine, true); ?>
                <?php elseif ($covCanGrant && !$covDeptIds): ?>
                    <p class="text-muted small mb-0"><?= e(__('coverage.empty_mine')) ?></p>
                <?php endif; ?>

                <?php if ($covReceived): ?>
                    <h6 class="mt-3"><?= e(__('coverage.received')) ?></h6>
                    <?php $covRenderList($covReceived, false); ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
  var grant = document.getElementById('coverageGrantForm');
  var box = document.getElementById('coverageUserPicker');
  var grantee = document.getElementById('coverage_grantee');
  var chosen = document.getElementById('coverageChosen');
  var empty = document.getElementById('coverageChosenEmpty');
  var emptyMine = document.getElementById('coverageEmptyMine');
  var pickLine = document.getElementById('coveragePickLine');
  var search = document.getElementById('coveragePickerSearch');
  var pickerEmpty = document.getElementById('coveragePickerEmpty');
  var modalEl = document.getElementById('coveragePickerModal');
  var assignText = <?= json_encode(__('users.dept_col_assign'), JSON_UNESCAPED_UNICODE) ?>;
  var draftText = <?= json_encode(__('users.dept_col_draft'), JSON_UNESCAPED_UNICODE) ?>;
  var removeText = <?= json_encode(__('departments.prep_delegates_remove'), JSON_UNESCAPED_UNICODE) ?>;
  var assignConfirm = <?= json_encode(__('users.dept_assign_confirm'), JSON_UNESCAPED_UNICODE) ?>;
  var assignConfirmTitle = <?= json_encode(__('users.dept_assign_confirm_title'), JSON_UNESCAPED_UNICODE) ?>;
  var draftConfirm = <?= json_encode(__('users.dept_draft_confirm'), JSON_UNESCAPED_UNICODE) ?>;
  var draftConfirmTitle = <?= json_encode(__('users.dept_draft_confirm_title'), JSON_UNESCAPED_UNICODE) ?>;
  var grantMsg = <?= json_encode(__('coverage.confirm'), JSON_UNESCAPED_UNICODE) ?>;
  var grantTitle = <?= json_encode(__('coverage.confirm_title'), JSON_UNESCAPED_UNICODE) ?>;
  var revokeMsg = <?= json_encode(__('coverage.revoke_confirm'), JSON_UNESCAPED_UNICODE) ?>;
  var revokeTitle = <?= json_encode(__('coverage.revoke_title'), JSON_UNESCAPED_UNICODE) ?>;
  function ask(msg, title) {
    if (window.DmsSwal && typeof window.DmsSwal.confirm === 'function') return window.DmsSwal.confirm(msg, title);
    return Promise.resolve(window.confirm(msg));
  }
  function confirmCheck(t, msg, title, afterOk) {
    t.checked = false;
    refresh();
    var row = t.closest('.coverage-user-row');
    var nameEl = row && row.querySelector('.coverage-user-name');
    var name = nameEl ? nameEl.textContent.trim() : '';
    ask(msg.replace(':dept', name), title).then(function (ok) {
      if (ok) {
        t.checked = true;
        if (afterOk) afterOk(t);
      }
      refresh();
    });
  }
  function exclusiveRow(keep) {
    if (!box) return;
    box.querySelectorAll('.coverage-user-row').forEach(function (row) {
      if (row === keep) return;
      row.querySelectorAll('.coverage-assign-check, .coverage-draft-check').forEach(function (el) {
        el.checked = false;
      });
    });
  }
  function refresh() {
    if (!box) return;
    var selected = null;
    box.querySelectorAll('.coverage-user-row').forEach(function (row) {
      var assign = row.querySelector('.coverage-assign-check');
      var draft = row.querySelector('.coverage-draft-check');
      var on = !!(assign && assign.checked) || !!(draft && draft.checked);
      row.classList.toggle('is-checked', on);
      if (on) selected = row;
    });
    if (grantee) grantee.value = selected ? (selected.getAttribute('data-user-id') || '0') : '0';
    if (chosen) {
      chosen.textContent = '';
      if (selected) {
        var nameEl = selected.querySelector('.coverage-user-name');
        var prefixEl = selected.querySelector('.coverage-user-prefix');
        var chip = document.createElement('div');
        chip.className = 'user-dept-chip is-primary';
        var stack = document.createElement('span');
        stack.className = 'coverage-user-text';
        if (prefixEl && prefixEl.textContent.trim() !== '') {
          var prefix = document.createElement('span');
          prefix.className = 'coverage-user-prefix';
          prefix.textContent = prefixEl.textContent.trim();
          stack.appendChild(prefix);
        }
        var title = document.createElement('span');
        title.className = 'user-dept-chip-name';
        title.textContent = nameEl ? nameEl.textContent : '';
        stack.appendChild(title);
        chip.appendChild(stack);
        var assign = selected.querySelector('.coverage-assign-check');
        var draft = selected.querySelector('.coverage-draft-check');
        if (assign && assign.checked) {
          var ab = document.createElement('span');
          ab.className = 'badge text-bg-light border';
          ab.textContent = assignText;
          chip.appendChild(ab);
        }
        if (draft && draft.checked) {
          var db = document.createElement('span');
          db.className = 'badge text-bg-warning';
          db.textContent = draftText;
          chip.appendChild(db);
        }
        var rm = document.createElement('button');
        rm.type = 'button';
        rm.className = 'btn-close btn-close-sm';
        rm.setAttribute('aria-label', removeText);
        rm.addEventListener('click', function () {
          selected.querySelectorAll('.coverage-assign-check, .coverage-draft-check').forEach(function (el) {
            el.checked = false;
          });
          refresh();
        });
        chip.appendChild(rm);
        chosen.appendChild(chip);
      }
    }
    var picked = !!selected;
    if (empty) {
      empty.classList.toggle('d-none', picked);
      empty.hidden = picked;
    }
    if (emptyMine) {
      emptyMine.classList.toggle('d-none', picked);
      emptyMine.hidden = picked;
    }
    if (pickLine) pickLine.classList.toggle('is-picked', picked);
  }
  if (box) {
    box.addEventListener('change', function (ev) {
      var t = ev.target;
      if (!t) return;
      if (t.classList.contains('coverage-assign-check') && t.checked) {
        confirmCheck(t, assignConfirm, assignConfirmTitle, function (el) {
          exclusiveRow(el.closest('.coverage-user-row'));
        });
        return;
      }
      if (t.classList.contains('coverage-draft-check') && t.checked) {
        confirmCheck(t, draftConfirm, draftConfirmTitle, function (el) {
          exclusiveRow(el.closest('.coverage-user-row'));
        });
        return;
      }
      refresh();
    });
  }
  if (search) {
    search.addEventListener('input', function () {
      var q = (search.value || '').trim().toLowerCase();
      var shown = 0;
      box.querySelectorAll('.coverage-user-row').forEach(function (row) {
        var match = q === '' || (row.getAttribute('data-search') || '').indexOf(q) !== -1;
        row.classList.toggle('d-none', !match);
        if (match) shown++;
      });
      if (pickerEmpty) pickerEmpty.classList.toggle('d-none', shown > 0);
    });
  }
  if (modalEl) {
    modalEl.addEventListener('shown.bs.modal', function () {
      if (search) search.focus();
    });
  }
  refresh();
  if (grant) {
    grant.addEventListener('submit', function (ev) {
      if (grant.dataset.ok === '1') return;
      ev.preventDefault();
      refresh();
      if (!grantee || grantee.value === '0') return;
      ask(grantMsg, grantTitle).then(function (ok) {
        if (!ok) return;
        grant.dataset.ok = '1';
        grant.submit();
      });
    });
  }
  var passShow = <?= json_encode(__('profile.password_show'), JSON_UNESCAPED_UNICODE) ?>;
  var passHide = <?= json_encode(__('profile.password_hide'), JSON_UNESCAPED_UNICODE) ?>;
  document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var wrap = btn.closest('.password-field');
      var input = wrap && wrap.querySelector('input');
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      var icon = btn.querySelector('i');
      if (icon) {
        icon.classList.toggle('bi-eye', !show);
        icon.classList.toggle('bi-eye-slash', show);
      }
      var label = show ? passHide : passShow;
      btn.setAttribute('title', label);
      btn.setAttribute('aria-label', label);
    });
  });
  document.querySelectorAll('.coverage-revoke-form').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (form.dataset.ok === '1') return;
      ev.preventDefault();
      ask(revokeMsg, revokeTitle).then(function (ok) {
        if (!ok) return;
        form.dataset.ok = '1';
        form.submit();
      });
    });
  });
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
