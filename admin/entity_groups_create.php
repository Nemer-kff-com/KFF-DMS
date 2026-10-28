<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('entity_groups.manage');
$pageTitle = __('groups.new');
ensure_entity_groups_schema();
$canManageAllEntities = ($user['role'] ?? '') === 'admin'
    || ($user['role'] ?? '') === 'archive'
    || (($user['role'] ?? '') === 'communications' && user_can('entity_groups.manage', $user));
$departments = $canManageAllEntities ? departments_list() : [];

if (!$canManageAllEntities && !(function_exists('user_has_assigned_departments') && user_has_assigned_departments($user))) {
    flash('danger', __('entities.department_required'));
    redirect(base_url('index.php'));
}

$isEdit = false;
$error = '';
$values = [
    'name' => '',
    'department_id' => $canManageAllEntities ? '' : (function_exists('user_primary_department_id') ? user_primary_department_id($user) : (int) $user['department_id']),
];
$selectedIds = [];
$selectedPayloads = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values['name'] = trim((string) ($_POST['name'] ?? ''));
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
    } else {
        $departmentId = function_exists('resolve_assigned_department_id')
            ? resolve_assigned_department_id($user, $_POST['department_id'] ?? 0, false)
            : (int) $user['department_id'];
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
            'INSERT INTO `tblentity_groups` (name, department_id) VALUES (?, ?)'
        )->execute([$values['name'], $departmentId]);
        $id = (int) db()->lastInsertId();
        save_entity_group_members($id, $selectedIds, $user);
        flash('success', __('groups.created'));
        redirect(base_url('admin/entity_groups.php'));
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e(__('groups.new')) ?></h1>
        <p class="page-sub"><?= e(__('groups.subtitle')) ?></p>
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
                <span><?= e(__('groups.new')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="admin-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/_entity_group_form.php'; ?>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
