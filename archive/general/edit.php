<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section('general', 'edit');
if (!can_edit_archive($user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('archive/general/index.php'));
}

$id = (int) ($_GET['id'] ?? 0);
$item = get_archive_item($id);
if (!$item || !user_can_edit_archive($item, $user)) {
    flash('danger', __('archive.not_found'));
    redirect(base_url('archive/general/index.php'));
}
$pageTitle = __('archive.edit');
$departments = departments_list();
if (function_exists('departments_visible_to_user')) {
    $departments = departments_visible_to_user($user, $departments);
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        save_archive_item($_POST, $user, $id);
        flash('success', __('archive.updated'));
        redirect(base_url('archive/general/view.php?id=' . $id));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('archive.edit')) ?></h1>
        <p class="page-sub"><?php if (!empty($item['archive_no'])): ?><span dir="ltr"><?= e((string) $item['archive_no']) ?></span> — <?php endif; ?><?= e(archive_title($item)) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('archive/general/view.php?id=' . $id)) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel admin-form-panel is-editing">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-pencil-square"></i></span>
                <span><?= e(__('archive.edit')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php $errorAlertId = 'archiveEditError'; require __DIR__ . '/../../includes/corr_form_error.php'; ?>
            <form method="post" class="admin-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/../../includes/archive_general_form.php'; ?>

                <div class="admin-form-actions">
                    <button type="submit" class="btn btn-kff"><i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?></button>
                    <a href="<?= e(base_url('archive/general/view.php?id=' . $id)) ?>" class="btn btn-outline-secondary"><?= e(__('action.cancel')) ?></a>
                </div>
</form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
