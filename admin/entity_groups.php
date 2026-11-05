<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('entity_groups.manage');
$pageTitle = $pageTitle ?? __('nav.entity_groups');
ensure_entity_groups_schema();
$canManageAllEntities = ($user['role'] ?? '') === 'admin'
    || ($user['role'] ?? '') === 'archive'
    || (($user['role'] ?? '') === 'communications' && user_can('entity_groups.manage', $user));

if (!$canManageAllEntities && !(function_exists('user_has_assigned_departments') && user_has_assigned_departments($user))) {
    flash('danger', __('entities.department_required'));
    redirect(base_url('index.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
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
    <div class="group-list">
        <?php foreach ($rows as $r): ?>
            <?php
            $memberCount = (int) ($r['member_count'] ?? 0);
            $canManage = user_can_manage_entity_group($r, $user);
            ?>
            <article class="group-card">
                <div class="group-card-head">
                    <div class="group-card-main">
                        <?php if ($canManage): ?>
                            <a class="group-card-name" href="<?= e(base_url('admin/entity_groups_edit.php?id=' . (int) $r['id'])) ?>"><?= e($r['name']) ?></a>
                        <?php else: ?>
                            <div class="group-card-name"><?= e($r['name']) ?></div>
                        <?php endif; ?>
                        <div class="group-card-meta">
                            <?php if (empty($r['department_id'])): ?>
                                <span class="badge text-bg-primary"><i class="bi bi-globe2 me-1"></i><?= e(__('entities.scope_public')) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-light text-dark border">
                                    <i class="bi bi-building me-1"></i>
                                    <?= e(department_display_name($r, 'department_name', 'department_name_en')) ?>
                                </span>
                            <?php endif; ?>
                            <span class="badge text-bg-light text-dark border"><?= e(__('groups.members_count')) ?>: <?= $memberCount ?></span>
                            <?= (int) $r['is_active']
                                ? '<span class="badge text-bg-success">' . e(__('common.active')) . '</span>'
                                : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?>
                        </div>
                    </div>
                    <?php if ($canManage): ?>
                    <div class="admin-actions">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/entity_groups_edit.php?id=' . (int) $r['id'])) ?>" title="<?= e(__('action.edit')) ?>" data-bs-toggle="tooltip" data-bs-placement="top">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form method="post" class="d-inline" data-confirm="<?= e(__('groups.confirm_delete')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit" title="<?= e(__('action.delete')) ?>" data-bs-toggle="tooltip" data-bs-placement="top">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                        <span class="text-muted small"><i class="bi bi-lock"></i></span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php if (!empty($groupEditOpen)): ?>
<div class="modal fade" id="groupEditModal" tabindex="-1" aria-labelledby="groupEditModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered group-edit-modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?= e(base_url('admin/entity_groups_edit.php?id=' . (int) $groupEditId)) ?>" class="admin-form mb-0">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $groupEditId ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="groupEditModalLabel">
                        <i class="bi bi-pencil-square me-1"></i><?= e(__('groups.edit')) ?>
                    </h5>
                    <a class="btn-close" href="<?= e(base_url('admin/entity_groups.php')) ?>" aria-label="<?= e(__('action.close')) ?>"></a>
                </div>
                <div class="modal-body">
                    <?php if (($groupEditError ?? '') !== ''): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-1"></i><?= e($groupEditError) ?>
                        </div>
                    <?php endif; ?>
                    <?php
                    $isEdit = true;
                    $values = $groupEditValues;
                    $error = (string) ($groupEditError ?? '');
                    require __DIR__ . '/_entity_group_form.php';
                    ?>
                </div>
                <div class="modal-footer">
                    <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/entity_groups.php')) ?>"><?= e(__('action.cancel')) ?></a>
                    <button type="submit" class="btn btn-kff">
                        <i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('groupEditModal');
    if (!el || !window.bootstrap || !bootstrap.Modal) return;
    bootstrap.Modal.getOrCreateInstance(el, { backdrop: 'static', keyboard: false }).show();
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
