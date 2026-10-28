<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if (!user_can('incoming.edit', $user)) {
    flash('danger', __('corr.no_perm_edit'));
    redirect(base_url('incoming/index.php'));
}
$id = (int) ($_GET['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || $corr['type'] !== 'incoming' || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('incoming/index.php'));
}
try {
    incoming_assert_can_edit_registration($user, $corr);
} catch (RuntimeException $e) {
    flash('danger', $e->getMessage());
    redirect(base_url('incoming/view.php?id=' . $id));
}
$pageTitle = __('corr.incoming_edit');
$type = 'incoming';
$departments = departments_list();
$categories = categories_list();
$linkables = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        save_correspondence('incoming', $_POST, $_FILES['attachments'] ?? $_FILES['attachment'] ?? null, $user, $id);
        flash('success', __('corr.updated_in'));
        redirect(base_url('incoming/view.php?id=' . $id));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageScripts = dms_page_scripts_with_date_picker($pageScripts ?? '');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('corr.incoming_edit')) ?></h1>
        <p class="page-sub"><?= e($corr['ref_number']) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('incoming/view.php?id=' . $id)) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel admin-form-panel is-editing">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-pencil-square"></i></span>
                <span><?= e(__('corr.incoming_edit')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php require __DIR__ . '/../includes/corr_form_error.php'; ?>
            <form method="post" enctype="multipart/form-data" class="admin-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/../includes/corr_form_fields.php'; ?>
                <?php
                $attachLabel = __('corr.add_attach');
                $attachHint = __('file.multi_hint');
                $notesValue = (string) ($_POST['notes'] ?? ($corr['notes'] ?? ''));
                require __DIR__ . '/../includes/corr_incoming_attach_notes.php';
                ?>

                <div class="admin-form-actions">
                    <button class="btn btn-kff" type="submit"><i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?></button>
                    <a href="<?= e(base_url('incoming/view.php?id=' . $id)) ?>" class="btn btn-outline-secondary"><?= e(__('action.cancel')) ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
