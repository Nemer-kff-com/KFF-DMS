<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if (!user_can('incoming.create', $user)) {
    flash('danger', __('corr.no_perm_add_in'));
    redirect(base_url('incoming/index.php'));
}
$pageTitle = __('corr.incoming_new');
$type = 'incoming';
$departments = departments_list();
$categories = categories_list();
$linkables = [];
$corr = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $id = save_correspondence('incoming', $_POST, $_FILES['attachments'] ?? $_FILES['attachment'] ?? null, $user);
        $savedMsg = (function_exists('user_can') && user_can('incoming.print_thermal', $user))
            ? __('corr.saved_in_thermal')
            : __('corr.saved_in');
        flash('success', $savedMsg);
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
        <h1 class="page-title"><?= e(__('corr.incoming_create')) ?></h1>
        <p class="page-sub"><?= e(str_replace(':sample', corr_ref_sample(''), __('corr.numbering_in'))) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('incoming/index.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-inbox"></i></span>
                <span><?= e(__('corr.incoming_create')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php $errorAlertId = 'incomingCreateError'; require __DIR__ . '/../includes/corr_form_error.php'; ?>
            <?php
            $inCreateDest = function_exists('corr_path_destination') ? corr_path_destination('incoming') : 'none';
            $inCreateHint = match ($inCreateDest) {
                'review' => __('in_issue_appr.desk_create_hint_review'),
                'approval' => __('in_issue_appr.desk_create_hint_approver'),
                default => __('in_issue_appr.desk_create_hint'),
            };
            ?>
            <div class="alert alert-info py-2 small no-print">
                <i class="bi bi-hourglass-split me-1"></i><?= e($inCreateHint) ?>
            </div>
            <form method="post" enctype="multipart/form-data" class="admin-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/../includes/corr_form_fields.php'; ?>
                <?php
                $attachLabel = __('corr.scan_attach');
                $attachHint = __('corr.scan_optional_hint');
                $notesValue = (string) ($_POST['notes'] ?? '');
                require __DIR__ . '/../includes/corr_incoming_attach_notes.php';
                ?>

                <div class="corr-issue-decide">
                    <div class="corr-decide-actions">
                    <button class="btn btn-kff" type="submit" data-keep-label data-confirm="<?= e(function_exists('corr_create_confirm_message') ? corr_create_confirm_message('incoming') : __('corr.confirm_save_in')) ?>"><i class="bi bi-check2 me-1"></i><?= e(__('corr.save_in')) ?></button>
                    <a href="<?= e(base_url('incoming/index.php')) ?>" class="btn btn-kff-soft" data-keep-label data-confirm="<?= e(__('corr.confirm_cancel_in')) ?>"><?= e(__('action.cancel')) ?></a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
