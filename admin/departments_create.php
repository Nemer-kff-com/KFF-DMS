<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_permission('admin.departments');
$pageTitle = __('departments.new');
ensure_departments_schema();

$isEdit = false;
$error = '';
$isSystemAdmin = can_manage_users($user);
$deptNextSortOrder = 10;
try {
    $deptNextSortOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM `tbldepartments`')->fetchColumn();
} catch (Throwable $e) {
    $deptNextSortOrder = 10;
}
$values = [
    'name' => '',
    'name_en' => '',
    'code' => '',
    'sort_order' => (string) $deptNextSortOrder,
    'incoming_prefix' => '',
    'outgoing_prefix' => '',
    'title_prefix' => '',
    'directed_from' => '',
    'job_title' => '',
    'principal_user_id' => 0,
    'prep_delegate_ids' => [],
    'is_signing_office' => 0,
    'signing_access_ids' => [],
];
$signingCandidateDepartments = departments_list(false);
$prepPickUsers = function_exists('outgoing_approval_active_users') ? outgoing_approval_active_users() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['name', 'name_en', 'code', 'incoming_prefix', 'outgoing_prefix', 'title_prefix', 'directed_from', 'job_title'] as $field) {
        $values[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $values['incoming_prefix'] = corr_sanitize_prefix($values['incoming_prefix']);
    $values['outgoing_prefix'] = corr_sanitize_prefix($values['outgoing_prefix']);
    $values['principal_user_id'] = (int) ($_POST['principal_user_id'] ?? 0);
    $values['prep_delegate_ids'] = array_values(array_filter(array_map('intval', (array) ($_POST['prep_delegate_ids'] ?? []))));
    $values['is_signing_office'] = 0;
    $values['signing_access_ids'] = [];
    $sortOrder = $isSystemAdmin
        ? (int) ($_POST['sort_order'] ?? $deptNextSortOrder)
        : $deptNextSortOrder;
    $values['sort_order'] = (string) $sortOrder;

    if ($values['name'] === '') {
        $error = __('common.name_required');
    } elseif (admin_name_exists('tbldepartments', 'name', $values['name'])) {
        $error = __('common.duplicate_name');
    } elseif (department_sort_order_taken($sortOrder)) {
        $error = __('departments.sort_duplicate');
    }

    if ($error === '') {
        ensure_departments_signing_office_schema();
        if (function_exists('ensure_outgoing_prep_authority_schema')) {
            ensure_outgoing_prep_authority_schema();
        }
        $officerName = (int) ($values['principal_user_id'] ?? 0) > 0 ? '' : (string) ($values['directed_from'] ?? '');
        $printFields = function_exists('outgoing_prep_principal_print_fields')
            ? outgoing_prep_principal_print_fields(
                (int) ($values['principal_user_id'] ?? 0),
                (string) ($values['job_title'] ?? ''),
                $officerName,
                (string) ($values['title_prefix'] ?? '')
            )
            : [
                'principal_user_id' => (int) ($values['principal_user_id'] ?? 0),
                'directed_from' => $values['directed_from'] !== '' ? $values['directed_from'] : null,
                'job_title' => $values['job_title'] !== '' ? $values['job_title'] : null,
            ];
        db()->prepare("INSERT INTO `tbldepartments` (name, name_en, code, sort_order, incoming_prefix, outgoing_prefix, title_prefix, directed_from, job_title, principal_user_id, is_signing_office) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([
                $values['name'],
                $values['name_en'] ?: null,
                $values['code'] ?: null,
                $sortOrder,
                $values['incoming_prefix'] !== '' ? $values['incoming_prefix'] : null,
                $values['outgoing_prefix'] !== '' ? $values['outgoing_prefix'] : null,
                !empty($printFields['title_prefix']) ? mb_substr((string) $printFields['title_prefix'], 0, 255) : ($values['title_prefix'] !== '' ? mb_substr($values['title_prefix'], 0, 255) : null),
                $printFields['directed_from'] ?? null,
                $printFields['job_title'] ?? null,
                !empty($printFields['principal_user_id']) ? (int) $printFields['principal_user_id'] : null,
                $values['is_signing_office'],
            ]);
        $newId = (int) db()->lastInsertId();
        try {
            if (function_exists('outgoing_prep_save_delegates')) {
                outgoing_prep_save_delegates($newId, $values['prep_delegate_ids'], (int) ($user['id'] ?? 0));
            }
            flash('success', __('departments.created'));
            redirect(base_url('admin/departments.php'));
        } catch (Throwable $e) {
            flash('danger', $e->getMessage());
            redirect(base_url('admin/departments_edit.php?id=' . $newId));
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('departments.new')) ?></h1>
        <p class="page-sub"><?= e(__('departments.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/departments.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-plus-circle"></i></span>
                <span><?= e(__('departments.new')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="admin-form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/_department_form.php'; ?>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
