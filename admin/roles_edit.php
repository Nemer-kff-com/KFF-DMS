<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.users');
ensure_roles_schema();
$pageTitle = __('roles.edit');

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$role = $id ? dms_role_get($id) : null;
if (!$role) {
    flash('danger', __('roles.not_found'));
    redirect(base_url('admin/roles.php'));
}

$isEdit = true;
$isSystemRole = (int) $role['is_system'] === 1;
$error = '';
$values = [
    'name_ar' => (string) $role['name_ar'],
    'name_en' => (string) ($role['name_en'] ?? ''),
    'description' => (string) ($role['description'] ?? ''),
    'base_role' => (string) $role['base_role'],
    'sort_order' => (string) $role['sort_order'],
    'is_active' => (int) $role['is_active'],
];
$usersCount = dms_roles_user_counts()[$id] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'name_ar' => (string) ($_POST['name_ar'] ?? ''),
        'name_en' => (string) ($_POST['name_en'] ?? ''),
        'description' => (string) ($_POST['description'] ?? ''),
        'base_role' => (string) $role['base_role'],
        'sort_order' => (string) ($_POST['sort_order'] ?? ''),
        'is_active' => $isSystemRole ? 1 : (isset($_POST['is_active']) ? 1 : 0),
    ];
    try {
        dms_role_save($values, $id, $user);
        if (!dms_role_is_admin($role)) {
            $postedPerms = is_array($_POST['perm'] ?? null) ? $_POST['perm'] : [];
            dms_role_permissions_save_one($id, $postedPerms, $user);
        }
        flash('success', __('roles.updated'));
        redirect(base_url('admin/roles_edit.php?id=' . $id));
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

$rolePermEditor = !dms_role_is_admin($role);
$rolePermCatalog = $rolePermEditor ? dms_permission_catalog() : [];
$rolePermHave = $rolePermEditor ? (dms_role_permissions_map()[$id] ?? []) : [];
if ($rolePermEditor && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rolePermHave = [];
    foreach ((array) ($_POST['perm'] ?? []) as $code) {
        $rolePermHave[(string) $code] = true;
    }
}
$rolePermBase = (string) ($values['base_role'] ?? $role['base_role']);

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('roles.edit')) ?></h1>
        <p class="page-sub"><?= e(dms_role_display_name($role)) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/roles.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-person-badge"></i></span>
                <span><?= e(dms_role_display_name($role)) ?></span>
                <?php if ($isSystemRole): ?>
                    <span class="badge text-bg-light border ms-2" title="<?= e(__('roles.system_tip')) ?>" data-bs-toggle="tooltip"><?= e(__('roles.system')) ?></span>
                <?php endif; ?>
                <span class="badge text-bg-light border ms-1"><i class="bi bi-people me-1"></i><?= (int) $usersCount ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $id ?>">
                <?php require __DIR__ . '/_role_form.php'; ?>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
