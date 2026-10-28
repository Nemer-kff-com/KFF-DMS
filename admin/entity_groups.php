<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('entity_groups.manage');
$pageTitle = __('nav.entity_groups');
ensure_entity_groups_schema();
$canManageAllEntities = ($user['role'] ?? '') === 'admin'
    || ($user['role'] ?? '') === 'archive'
    || (($user['role'] ?? '') === 'communications' && user_can('entity_groups.manage', $user));

if (!$canManageAllEntities && !(function_exists('user_has_assigned_departments') && user_has_assigned_departments($user))) {
    flash('danger', __('entities.department_required'));
    redirect(base_url('index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $group = $id ? get_entity_group($id) : null;
    if (($_POST['action'] ?? '') !== 'delete' || !$group) {
        flash('danger', __('common.not_found'));
        redirect(base_url('admin/entity_groups.php'));
    }
    if (!user_can_manage_entity_group($group, $user)) {
        flash('danger', __('auth.forbidden'));
        redirect(base_url('admin/entity_groups.php'));
    }
    db()->prepare('DELETE FROM `tblentity_group_members` WHERE group_id=?')->execute([$id]);
    db()->prepare('UPDATE `tbloutgoing_batches` SET entity_group_id = NULL WHERE entity_group_id=?')->execute([$id]);
    db()->prepare('DELETE FROM `tblentity_groups` WHERE id=?')->execute([$id]);
    flash('success', __('groups.deleted'));
    redirect(base_url('admin/entity_groups.php'));
}

$rows = entity_groups_list(false, $user);

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('groups.title')) ?></h1>
        <p class="page-sub"><?= e(__('groups.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <span class="admin-count-badge"><i class="bi bi-people me-1"></i><?= count($rows) ?></span>
        <?= btn_icon_add(base_url('admin/entity_groups_create.php'), __('groups.new')) ?>
    </div>
</div>

<div class="card-panel page-narrow">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-people me-1"></i><?= e(__('groups.title')) ?></span>
        <span class="badge text-bg-light text-dark"><?= count($rows) ?></span>
    </div>
    <?php if (!$rows): ?>
        <div class="admin-empty">
            <i class="bi bi-people"></i>
            <p><?= e(__('groups.empty')) ?></p>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table admin-table mb-0 align-middle">
            <thead>
                <tr>
                    <th><?= e(__('groups.name')) ?></th>
                    <th><?= e(__('entities.scope')) ?></th>
                    <th><?= e(__('groups.members_count')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                    <th class="text-end"><?= e(__('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="admin-entity-name"><?= e($r['name']) ?></td>
                    <td>
                        <?php if (empty($r['department_id'])): ?>
                            <span class="badge text-bg-primary"><i class="bi bi-globe2 me-1"></i><?= e(__('entities.scope_public')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-light text-dark border">
                                <i class="bi bi-building me-1"></i>
                                <?= e(department_display_name($r, 'department_name', 'department_name_en')) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) ($r['member_count'] ?? 0) ?></td>
                    <td>
                        <?= (int) $r['is_active']
                            ? '<span class="badge text-bg-success">' . e(__('common.active')) . '</span>'
                            : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if (user_can_manage_entity_group($r, $user)): ?>
                        <div class="admin-actions">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/entity_groups_edit.php?id=' . (int) $r['id'])) ?>" title="<?= e(__('action.edit')) ?>">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="post" class="d-inline" data-confirm="<?= e(__('groups.confirm_delete')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit" title="<?= e(__('action.delete')) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                            <span class="text-muted small"><i class="bi bi-lock"></i></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
