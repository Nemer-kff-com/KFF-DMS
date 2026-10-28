<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('entities.manage');
$pageTitle = __('entities.edit');
ensure_entities_schema();
$entityScopeReady = entities_department_scope_available();
$canManageAllEntities = function_exists('user_manages_all_entities') && user_manages_all_entities($user);
$isSystemAdmin = can_manage_users($user);
$departments = $canManageAllEntities ? departments_list() : [];

if (!$canManageAllEntities && !(function_exists('user_has_assigned_departments') && user_has_assigned_departments($user))) {
    flash('danger', __('entities.department_required'));
    redirect(base_url('index.php'));
}

$id = (int) (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        ? ($_POST['id'] ?? $_GET['id'] ?? 0)
        : ($_GET['id'] ?? 0)
);
$entity = $id ? get_entity($id) : null;
if (!$entity) {
    flash('danger', __('common.not_found'));
    redirect(base_url('admin/entities.php'));
}
if (!user_can_manage_entity($entity, $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('admin/entities.php'));
}

$isEdit = true;
$error = '';
$values = [
    'name_ar' => (string) ($entity['name_ar'] ?? ''),
    'directed_to' => (string) ($entity['directed_to'] ?? ''),
    'title_prefix' => (string) ($entity['title_prefix'] ?? ''),
    'job_title' => (string) ($entity['job_title'] ?? ''),
    'honorific' => (string) ($entity['honorific'] ?? ''),
    'phone' => (string) ($entity['phone'] ?? ''),
    'mobile' => (string) ($entity['mobile'] ?? ''),
    'email' => (string) ($entity['email'] ?? ''),
    'address' => (string) ($entity['address'] ?? ''),
    'location' => (string) ($entity['location'] ?? ''),
    'notes' => (string) ($entity['notes'] ?? ''),
    'department_id' => $entity['department_id'] ?? '',
    'sort_order' => (string) (int) ($entity['sort_order'] ?? 0),
    'is_active' => (int) ($entity['is_active'] ?? 1),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$entityScopeReady) {
        flash('danger', __('entities.migration_pending'));
        redirect(base_url('admin/entities.php'));
    }

    foreach (['name_ar', 'directed_to', 'title_prefix', 'job_title', 'phone', 'mobile', 'email', 'address', 'location', 'notes'] as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $values['location'] = entity_maps_url_normalize($values['location']);
    $values['honorific'] = entity_honorific_from_input($_POST);
    $values['title_prefix'] = entity_title_prefix_from_input($_POST);
    $values['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    if ($isSystemAdmin) {
        $values['sort_order'] = (string) (int) ($_POST['sort_order'] ?? $values['sort_order']);
    }

    if ($canManageAllEntities) {
        $rawDept = trim((string) ($_POST['department_id'] ?? ''));
        // 0 أو فارغ = جهة عامة (جميع الأقسام)
        $departmentId = ($rawDept === '' || $rawDept === '0') ? null : (int) $rawDept;
        if ($departmentId !== null && $departmentId < 1) {
            $departmentId = null;
        }
        $values['department_id'] = $departmentId ?? '';
        if ($departmentId && !department_exists($departmentId, $departments)) {
            $error = __('entities.invalid_department');
        }
    } else {
        $departmentId = function_exists('resolve_assigned_department_id')
            ? resolve_assigned_department_id($user, $_POST['department_id'] ?? 0, false)
            : (int) $user['department_id'];
        $values['department_id'] = $departmentId;
    }

    if ($error === '' && $values['name_ar'] !== '' && entity_name_exists_in_scope($values['name_ar'], $departmentId, $id)) {
        $error = __('entities.duplicate');
    }

    if ($error === '' && $values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $error = __('entities.email_invalid');
    }

    if ($error === '' && $values['location'] !== '' && !entity_maps_url_valid($values['location'])) {
        $error = __('entities.location_invalid');
    }

    $sortOrder = $isSystemAdmin
        ? (int) $values['sort_order']
        : (int) ($entity['sort_order'] ?? 0);
    $currentSort = (int) ($entity['sort_order'] ?? 0);
    if ($error === '' && $isSystemAdmin && $sortOrder !== $currentSort && entity_sort_order_taken($sortOrder, $id)) {
        $error = __('entities.sort_duplicate');
    }

    if ($error === '') {
        db()->prepare(
            "UPDATE `tblentities`
             SET name_ar=?, name_en=NULL, directed_to=?, title_prefix=?, job_title=?, honorific=?, phone=?, mobile=?, email=?, address=?, location=?, notes=?,
                 department_id=?, sort_order=?, is_active=?
             WHERE id=?"
        )->execute([
            $values['name_ar'],
            $values['directed_to'] ?: null,
            $values['title_prefix'] !== '' ? $values['title_prefix'] : null,
            $values['job_title'] ?: null,
            $values['honorific'] !== '' ? $values['honorific'] : null,
            $values['phone'] ?: null,
            $values['mobile'] ?: null,
            $values['email'] ?: null,
            $values['address'] ?: null,
            $values['location'] ?: null,
            $values['notes'] ?: null,
            $departmentId,
            $sortOrder,
            $values['is_active'],
            $id,
        ]);
        flash('success', __('entities.updated'));
        redirect(base_url('admin/entities.php'));
    }
}

require __DIR__ . '/../includes/header.php';
?>
<?php if (!$entityScopeReady): ?>
    <div class="alert alert-warning entity-edit-page">
        <i class="bi bi-exclamation-triangle me-1"></i><?= e(__('entities.migration_pending')) ?>
    </div>
<?php endif; ?>

<div class="entity-edit-page">
    <div class="entity-form-page-shell">
        <form method="post" class="admin-form entity-form-page-card-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $id ?>">

            <div class="entity-form-page-shell-header">
                <div class="entity-form-page-shell-heading">
                    <h5 class="entity-form-page-shell-title mb-0">
                        <i class="bi bi-pencil-square me-1"></i><?= e(__('entities.edit')) ?>
                    </h5>
                    <p class="entity-form-page-shell-sub mb-0"><?= e($entity['name_ar']) ?></p>
                </div>
                <a class="btn btn-sm btn-outline-secondary entity-form-page-shell-back" href="<?= e(base_url('admin/entities.php')) ?>" title="<?= e(__('action.back')) ?>">
                    <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?>"></i>
                </a>
            </div>

            <div class="entity-form-page-shell-body entity-create-modal-body entity-create-modal-body-compact">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger mb-3">
                        <i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?>
                    </div>
                <?php endif; ?>
                <?php
                $entityFormCompact = true;
                $entityFormHideActions = true;
                require __DIR__ . '/_entity_form.php';
                ?>
            </div>

            <div class="entity-form-page-shell-footer">
                <a href="<?= e(base_url('admin/entities.php')) ?>" class="btn btn-outline-secondary"><?= e(__('action.cancel')) ?></a>
                <button class="btn btn-kff" type="submit" <?= $entityScopeReady ? '' : 'disabled' ?>>
                    <i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
