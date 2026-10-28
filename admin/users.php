<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.users');
$pageTitle = __('nav.users');
ensure_users_profile_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'delete' && $id) {
        if ($id === (int) $user['id']) {
            flash('danger', __('users.cannot_delete_self'));
        } else {
            try {
                db()->prepare("DELETE FROM `tblusers` WHERE id = ?")->execute([$id]);
                dms_user_permissions_delete_for($id);
                flash('success', __('users.deleted'));
            } catch (PDOException $e) {
                flash('danger', __('users.cannot_delete'));
            }
        }
    } elseif ($action === 'invite' && $id) {
        require_once __DIR__ . '/../config/user_invite.php';
        $inviteError = null;
        $sentTo = null;
        if (dms_user_invite_send($id, $inviteError, (int) $user['id'], $sentTo)) {
            $msg = $sentTo
                ? str_replace(':email', $sentTo, __('users.invite_sent_to'))
                : __('users.invite_sent');
            flash('success', $msg);
        } else {
            flash('danger', $inviteError !== null && $inviteError !== '' ? $inviteError : __('users.invite_failed'));
        }
    }
    $backRole = (int) ($_POST['role_id'] ?? $_GET['role_id'] ?? 0);
    redirect(base_url('admin/users.php' . ($backRole > 0 ? '?role_id=' . $backRole : '')));
}

// توافق مع الروابط القديمة: admin/users.php?edit=5
$legacyEditId = (int) ($_GET['edit'] ?? 0);
if ($legacyEditId) {
    redirect(base_url('admin/users_edit.php?id=' . $legacyEditId));
}

$roleFilterId = (int) ($_GET['role_id'] ?? 0);
$roleFilter = ($roleFilterId > 0 && function_exists('dms_role_get')) ? dms_role_get($roleFilterId) : null;
if ($roleFilterId > 0 && !$roleFilter) {
    $roleFilterId = 0;
}
$usersSql = "SELECT u.*, d.name AS department_name, d.name_en AS department_name_en
     FROM `tblusers` u
     LEFT JOIN `tbldepartments` d ON d.id = u.department_id";
if ($roleFilterId > 0) {
    $usersStmt = db()->prepare($usersSql . ' WHERE u.role_id = ? ORDER BY u.sort_order ASC, u.name_ar ASC, u.id ASC');
    $usersStmt->execute([$roleFilterId]);
    $users = $usersStmt->fetchAll();
} else {
    $users = db()->query($usersSql . ' ORDER BY u.sort_order ASC, u.name_ar ASC, u.id ASC')->fetchAll();
}
$userIds = array_map(static fn($row) => (int) $row['id'], $users);
$usersDeptMap = function_exists('users_departments_map')
    ? users_departments_map($userIds)
    : [];
$usersDraftMap = function_exists('outgoing_prep_users_delegate_map')
    ? outgoing_prep_users_delegate_map($userIds)
    : [];
$usersPrincipalMap = function_exists('outgoing_prep_users_principal_map')
    ? outgoing_prep_users_principal_map($userIds)
    : [];

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('users.title')) ?></h1>
        <p class="page-sub"><?= $roleFilter ? e(str_replace(':name', dms_role_display_name($roleFilter), __('users.role_filter'))) : e(__('users.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <span class="admin-count-badge"><i class="bi bi-people me-1"></i><?= count($users) ?></span>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(base_url('admin/users_print.php' . ($roleFilterId > 0 ? '?role_id=' . $roleFilterId : ''))) ?>" target="_blank" rel="noopener">
            <i class="bi bi-printer me-1"></i><?= e(__('users.print')) ?>
        </a>
        <?= btn_icon_add(base_url('admin/users_create.php'), __('users.new')) ?>
    </div>
</div>

<div class="card-panel page-narrow">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-ul me-1"></i><?= e(__('users.list')) ?></span>
        <span class="d-flex align-items-center gap-2">
            <?php if ($roleFilter): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/users.php')) ?>"><?= e(__('users.role_filter_clear')) ?></a>
            <?php endif; ?>
            <span class="badge text-bg-light text-dark"><?= count($users) ?></span>
        </span>
    </div>
    <?php if (!$users): ?>
        <div class="admin-empty">
            <i class="bi bi-people"></i>
            <p><?= e(__('common.no_data')) ?></p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table admin-table mb-0 align-middle">
            <thead>
                <tr>
                    <th class="text-center"><?= e(__('users.sort')) ?></th>
                    <th class="admin-user-photo-col"><?= e(__('users.photo_col')) ?></th>
                    <th class="admin-user-name-col"><?= e(__('users.user_col')) ?></th>
                    <th><?= e(__('users.role')) ?></th>
                    <th><?= e(__('users.department_primary_col')) ?></th>
                    <th><?= e(__('users.departments_support')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                    <th class="text-end"><?= e(__('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <?php
                $listPhoto = function_exists('user_photo_url') ? user_photo_url($u) : '';
                $editUrl = base_url('admin/users_edit.php?id=' . (int) $u['id']);
                ?>
                <tr>
                    <td class="text-center"><?= (int) ($u['sort_order'] ?? 0) ?></td>
                    <td class="admin-user-photo-col">
                        <a href="<?= e($editUrl) ?>" class="admin-user-avatar-link" title="<?= e(__('action.edit')) ?>">
                            <?php if ($listPhoto !== ''): ?>
                                <img src="<?= e($listPhoto) ?>" alt="" class="admin-user-avatar">
                            <?php else: ?>
                                <span class="admin-user-avatar is-fallback"><i class="bi bi-person"></i></span>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td class="admin-user-name-col">
                        <?php
                        $listPrefix = trim((string) ($u['title_prefix'] ?? ''));
                        $listJob = function_exists('user_job_title') ? user_job_title($u) : trim((string) ($u['job_title'] ?? ''));
                        ?>
                        <?php if ($listPrefix !== ''): ?>
                            <div class="admin-entity-sub"><?= e($listPrefix) ?></div>
                        <?php endif; ?>
                        <div class="admin-entity-name"><?= e(user_display_name($u)) ?></div>
                        <?php if ($listJob !== ''): ?>
                            <div class="admin-entity-sub"><?= e($listJob) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= e(user_role_label($u)) ?>
                        <?php $ovCount = dms_user_permission_override_count($u); ?>
                        <?php if ($ovCount > 0): ?>
                            <span class="badge text-bg-warning ms-1" title="<?= e(str_replace(':n', (string) $ovCount, __('perm.user_ov_count'))) ?>" data-bs-toggle="tooltip">+<?= $ovCount ?></span>
                        <?php endif; ?>
                    </td>
                    <?php
                    $uid = (int) $u['id'];
                    $uAssigned = $usersDeptMap[$uid] ?? [];
                    $assignedIds = [];
                    foreach ($uAssigned as $ud) {
                        $aid = (int) ($ud['id'] ?? 0);
                        if ($aid > 0) {
                            $assignedIds[$aid] = true;
                        }
                    }
                    $uDepts = $uAssigned;
                    if (!$uDepts && department_display_name($u, 'department_name', 'department_name_en')) {
                        $uDepts = [[
                            'id' => (int) ($u['department_id'] ?? 0),
                            'name' => (string) ($u['department_name'] ?? ''),
                            'name_en' => (string) ($u['department_name_en'] ?? ''),
                            'is_primary' => 1,
                        ]];
                    }
                    $uDrafts = $usersDraftMap[$uid] ?? [];
                    $draftIds = [];
                    foreach ($uDrafts as $dd) {
                        $draftIds[(int) $dd['id']] = $dd;
                    }
                    $uPrincipals = $usersPrincipalMap[$uid] ?? [];
                    $principalIds = [];
                    foreach ($uPrincipals as $pd) {
                        $principalIds[(int) $pd['id']] = $pd;
                    }
                    $uPrimary = null;
                    $homeId = (int) ($u['department_id'] ?? 0);
                    if ($homeId > 0) {
                        foreach ($uDepts as $ud) {
                            if ((int) ($ud['id'] ?? 0) === $homeId) {
                                $uPrimary = $ud;
                                break;
                            }
                        }
                        if ($uPrimary === null) {
                            foreach ($uDepts as $ud) {
                                if (!empty($ud['is_primary'])) {
                                    $uPrimary = $ud;
                                    break;
                                }
                            }
                        }
                        if ($uPrimary === null) {
                            $uPrimary = [
                                'id' => $homeId,
                                'name' => (string) ($u['department_name'] ?? ''),
                                'name_en' => (string) ($u['department_name_en'] ?? ''),
                                'is_primary' => 1,
                            ];
                        }
                    } elseif ($uDepts) {
                        foreach ($uDepts as $ud) {
                            if (!empty($ud['is_primary'])) {
                                $uPrimary = $ud;
                                break;
                            }
                        }
                        if ($uPrimary === null) {
                            $uPrimary = $uDepts[0];
                        }
                    }
                    $primaryId = (int) ($uPrimary['id'] ?? 0);
                    $primaryAssigned = $primaryId > 0 && isset($assignedIds[$primaryId]);
                    $primaryDraft = $primaryId > 0 && isset($draftIds[$primaryId]);
                    $primaryPrincipal = $primaryId > 0 && isset($principalIds[$primaryId]);
                    $uSupport = [];
                    foreach ($uAssigned as $ud) {
                        $did = (int) ($ud['id'] ?? 0);
                        if ($did <= 0 || $did === $primaryId) {
                            continue;
                        }
                        $uSupport[$did] = [
                            'id' => $did,
                            'name' => (string) ($ud['name'] ?? ''),
                            'name_en' => (string) ($ud['name_en'] ?? ''),
                            'grant_assign' => true,
                            'grant_draft' => isset($draftIds[$did]),
                            'grant_principal' => isset($principalIds[$did]),
                        ];
                    }
                    foreach ($uDrafts as $dd) {
                        $did = (int) ($dd['id'] ?? 0);
                        if ($did <= 0 || $did === $primaryId) {
                            continue;
                        }
                        if (!isset($uSupport[$did])) {
                            $uSupport[$did] = [
                                'id' => $did,
                                'name' => (string) ($dd['name'] ?? ''),
                                'name_en' => (string) ($dd['name_en'] ?? ''),
                                'grant_assign' => false,
                                'grant_draft' => true,
                                'grant_principal' => isset($principalIds[$did]),
                            ];
                        } else {
                            $uSupport[$did]['grant_draft'] = true;
                        }
                    }
                    foreach ($uPrincipals as $pd) {
                        $did = (int) ($pd['id'] ?? 0);
                        if ($did <= 0 || $did === $primaryId) {
                            continue;
                        }
                        if (!isset($uSupport[$did])) {
                            $uSupport[$did] = [
                                'id' => $did,
                                'name' => (string) ($pd['name'] ?? ''),
                                'name_en' => (string) ($pd['name_en'] ?? ''),
                                'grant_assign' => false,
                                'grant_draft' => isset($draftIds[$did]),
                                'grant_principal' => true,
                            ];
                        } else {
                            $uSupport[$did]['grant_principal'] = true;
                        }
                    }
                    $uSupport = array_values($uSupport);
                    $renderDeptFlags = static function (bool $assign, bool $draft, bool $principal): void {
                        if ($principal): ?>
                            <span class="user-dept-badge-flag is-principal"><?= e(__('users.dept_col_principal')) ?></span>
                        <?php endif;
                        if ($assign): ?>
                            <span class="user-dept-badge-flag is-assign"><?= e(__('users.dept_col_assign')) ?></span>
                        <?php endif;
                        if ($draft): ?>
                            <span class="user-dept-badge-flag is-draft"><?= e(__('users.dept_col_draft')) ?></span>
                        <?php endif;
                    };
                    ?>
                    <td class="user-dept-cell">
                        <?php if ($uPrimary): ?>
                            <?php
                            $primaryTips = [__('users.department_primary_tip')];
                            if ($primaryAssigned) {
                                $primaryTips[] = __('users.dept_assign_tip');
                            }
                            if ($primaryDraft) {
                                $primaryTips[] = __('users.dept_draft_tip');
                            }
                            if ($primaryPrincipal) {
                                $primaryTips[] = __('users.dept_principal_tip');
                            }
                            ?>
                            <div class="user-dept-item is-primary" title="<?= e(implode(' — ', $primaryTips)) ?>">
                                <div class="user-dept-item-name"><?= e(department_display_name($uPrimary)) ?></div>
                                <?php if ($primaryAssigned || $primaryDraft || $primaryPrincipal): ?>
                                    <div class="user-dept-item-flags">
                                        <?php $renderDeptFlags($primaryAssigned, $primaryDraft, $primaryPrincipal); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="user-dept-cell">
                        <?php if ($uSupport): ?>
                            <div class="user-dept-list">
                                <?php
                                $shown = 0;
                                $maxShow = 4;
                                $extra = max(0, count($uSupport) - $maxShow);
                                foreach ($uSupport as $ud):
                                    if ($shown >= $maxShow) {
                                        break;
                                    }
                                    $shown++;
                                    $tips = [];
                                    if (!empty($ud['grant_assign'])) {
                                        $tips[] = __('users.dept_assign_tip');
                                    }
                                    if (!empty($ud['grant_draft'])) {
                                        $tips[] = __('users.dept_draft_tip');
                                    }
                                    if (!empty($ud['grant_principal'])) {
                                        $tips[] = __('users.dept_principal_tip');
                                    }
                                ?>
                                    <div class="user-dept-item" title="<?= e(implode(' — ', $tips)) ?>">
                                        <div class="user-dept-item-name"><?= e(department_display_name($ud)) ?></div>
                                        <?php if (!empty($ud['grant_assign']) || !empty($ud['grant_draft']) || !empty($ud['grant_principal'])): ?>
                                            <div class="user-dept-item-flags">
                                                <?php $renderDeptFlags(!empty($ud['grant_assign']), !empty($ud['grant_draft']), !empty($ud['grant_principal'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($extra > 0): ?>
                                    <div class="user-dept-item is-more">+<?= (int) $extra ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= (int)$u['is_active']
                            ? '<span class="badge text-bg-success">' . e(__('common.active')) . '</span>'
                            : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="admin-actions">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/users_edit.php?id=' . (int) $u['id'])) ?>" title="<?= e(__('action.edit')) ?>">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('admin/user_permissions.php?id=' . (int) $u['id'])) ?>" title="<?= e(__('perm.user_title')) ?>">
                                <i class="bi bi-shield-check"></i>
                            </a>
                            <form method="post" class="d-inline" data-confirm="<?= e(str_replace(':email', (string) $u['email'], __('users.confirm_invite'))) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="invite">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-outline-success" type="submit" title="<?= e(__('users.invite')) ?>">
                                    <i class="bi bi-envelope"></i>
                                </button>
                            </form>
                            <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                            <form method="post" class="d-inline" data-confirm="<?= e(__('users.confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="<?= e(__('action.delete')) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
