<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.users');
ensure_roles_schema();
$pageTitle = __('roles.new');

$isEdit = false;
$isSystemRole = false;
$error = '';
$values = [
    'name_ar' => '',
    'name_en' => '',
    'description' => '',
    'base_role' => '',
    'sort_order' => '',
    'is_active' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = [
        'name_ar' => (string) ($_POST['name_ar'] ?? ''),
        'name_en' => (string) ($_POST['name_en'] ?? ''),
        'description' => (string) ($_POST['description'] ?? ''),
        'sort_order' => (string) ($_POST['sort_order'] ?? ''),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
    ];
    try {
        $newId = dms_role_save($values, null, $user);
        flash('success', __('roles.created'));
        redirect(base_url('admin/roles_edit.php?id=' . $newId));
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow page-narrow-sm">
    <div>
        <h1 class="page-title"><?= e(__('roles.new')) ?></h1>
        <p class="page-sub"><?= e(__('roles.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/roles.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow page-narrow-sm">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-person-badge"></i></span>
                <span><?= e(__('roles.new')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="admin-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/_role_form.php'; ?>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
