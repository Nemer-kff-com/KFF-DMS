<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.users');
ensure_roles_schema();
$pageTitle = __('roles.title');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete' && $id) {
        try {
            dms_role_delete($id, $user);
            flash('success', __('roles.deleted'));
        } catch (InvalidArgumentException $e) {
            flash('danger', $e->getMessage());
        }
    }
    redirect(base_url('admin/roles.php'));
}

$roles = dms_roles_list();
$counts = dms_roles_user_counts();

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('roles.title')) ?></h1>
        <p class="page-sub"><?= e(__('roles.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <span class="admin-count-badge"><i class="bi bi-person-badge me-1"></i><?= count($roles) ?></span>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/permissions.php')) ?>">
            <i class="bi bi-shield-check me-1"></i><?= e(__('perm.title')) ?>
        </a>
        <?= btn_icon_add(base_url('admin/roles_create.php'), __('roles.new')) ?>
    </div>
</div>

<div class="card-panel page-narrow">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><i class="bi bi-list-ul me-1"></i><?= e(__('roles.list')) ?></span>
        <span class="badge text-bg-light text-dark"><?= count($roles) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table admin-table mb-0 align-middle">
            <thead>
                <tr>
                    <th><?= e(__('roles.name_col')) ?></th>
                    <th class="text-center"><?= e(__('roles.users_col')) ?></th>
                    <th><?= e(__('common.status')) ?></th>
                    <th class="text-end"><?= e(__('common.actions')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($roles as $r): ?>
                <?php
                $rid = (int) $r['id'];
                $isSystem = (int) $r['is_system'] === 1;
                $n = $counts[$rid] ?? 0;
                $otherName = dms_lang_code() === 'en' ? trim((string) $r['name_ar']) : trim((string) ($r['name_en'] ?? ''));
                ?>
                <tr>
                    <td>
                        <div class="admin-entity-name">
                            <?= e(dms_role_display_name($r)) ?>
                            <?php if ($isSystem): ?>
                                <span class="badge text-bg-light border ms-1" title="<?= e(__('roles.system_tip')) ?>" data-bs-toggle="tooltip"><?= e(__('roles.system')) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($otherName !== '' && $otherName !== dms_role_display_name($r)): ?>
                            <div class="admin-entity-sub"><?= e($otherName) ?></div>
                        <?php endif; ?>
                        <?php if (trim((string) ($r['description'] ?? '')) !== ''): ?>
                            <div class="admin-entity-sub"><?= e((string) $r['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <a class="badge text-bg-light border text-decoration-none" href="<?= e(base_url('admin/users.php?role_id=' . $rid)) ?>" title="<?= e(__('roles.view_users')) ?>" data-bs-toggle="tooltip"><?= (int) $n ?></a>
                    </td>
                    <td>
                        <?= (int) $r['is_active'] === 1
                            ? '<span class="badge text-bg-success">' . e(__('common.active')) . '</span>'
                            : '<span class="badge text-bg-secondary">' . e(__('common.inactive')) . '</span>' ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <div class="admin-actions">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('admin/roles_edit.php?id=' . $rid)) ?>" title="<?= e(__('action.edit')) ?>">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php if (!$isSystem): ?>
                                <?php if ($n > 0): ?>
                                    <span class="d-inline-block" tabindex="0" title="<?= e(__('roles.cannot_delete_used')) ?>" data-bs-toggle="tooltip">
                                        <button class="btn btn-sm btn-outline-danger" type="button" disabled><i class="bi bi-trash"></i></button>
                                    </span>
                                <?php else: ?>
                                    <form method="post" class="d-inline" data-confirm="<?= e(str_replace(':name', dms_role_display_name($r), __('roles.confirm_delete'))) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $rid ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" title="<?= e(__('action.delete')) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
