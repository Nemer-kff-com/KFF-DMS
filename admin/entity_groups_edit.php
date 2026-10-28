<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('entity_groups.manage');
ensure_entity_groups_schema();
$canManageAllEntities = ($user['role'] ?? '') === 'admin'
    || ($user['role'] ?? '') === 'archive'
    || (($user['role'] ?? '') === 'communications' && user_can('entity_groups.manage', $user));
$departments = $canManageAllEntities ? departments_list() : [];

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$group = $id ? get_entity_group($id) : null;
if (!$group || !user_can_manage_entity_group($group, $user)) {
    flash('danger', __('common.not_found'));
    redirect(base_url('admin/entity_groups.php'));
}

$pageTitle = __('groups.edit');
$isEdit = true;
$error = '';
$values = [
    'name' => $group['name'],
    'department_id' => $group['department_id'] ?? '',
    'is_active' => $group['is_active'],
];
$selectedIds = entity_group_member_ids($id);
$selectedPayloads = [];
if ($selectedIds) {
    $selectedRows = entities_list(true, $user, null, ['ids' => $selectedIds, 'limit' => 200, 'picker' => true]);
    $byId = [];
    foreach ($selectedRows as $row) {
        $byId[(int) $row['id']] = entity_picker_payload($row, false);
    }
    foreach ($selectedIds as $sid) {
        if (isset($byId[$sid])) {
            $selectedPayloads[] = $byId[$sid];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values['name'] = trim((string) ($_POST['name'] ?? ''));
    $values['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $selectedIds = array_map('intval', $_POST['entity_ids'] ?? []);
    $selectedPayloads = [];
    if ($selectedIds) {
        $selectedRows = entities_list(true, $user, null, ['ids' => $selectedIds, 'limit' => 200, 'picker' => true]);
        $byId = [];
        foreach ($selectedRows as $row) {
            $byId[(int) $row['id']] = entity_picker_payload($row, false);
        }
        foreach ($selectedIds as $sid) {
            if (isset($byId[$sid])) {
                $selectedPayloads[] = $byId[$sid];
            }
        }
    }

    if ($canManageAllEntities) {
        $departmentId = (int) ($_POST['department_id'] ?? 0) ?: null;
        if ($departmentId && !department_exists($departmentId, $departments)) {
            $error = __('entities.invalid_department');
        }
        $values['department_id'] = $departmentId;
    } else {
        $departmentId = (int) $group['department_id'];
        $values['department_id'] = $departmentId;
    }

    if ($error === '' && $values['name'] === '') {
        $error = __('groups.name_required');
    }
    if ($error === '' && !$selectedIds) {
        $error = __('groups.members_required');
    }

    if ($error === '') {
        db()->prepare(
            'UPDATE `tblentity_groups` SET name=?, department_id=?, is_active=? WHERE id=?'
        )->execute([$values['name'], $departmentId, $values['is_active'], $id]);
        save_entity_group_members($id, $selectedIds, $user);
        flash('success', __('groups.updated'));
        redirect(base_url('admin/entity_groups.php'));
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('groups.edit')) ?></h1>
        <p class="page-sub"><?= e($group['name']) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/entity_groups.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-people"></i></span>
                <span><?= e(__('groups.edit')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <?php require __DIR__ . '/_entity_group_form.php'; ?>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
