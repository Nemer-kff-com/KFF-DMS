<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if (!user_can('internal.create', $user)) {
    flash('danger', __('internal.no_perm_add'));
    redirect(base_url('internal/index.php'));
}
$pageTitle = __('internal.new');
$allDepartments = departments_list();
$fromDepartments = $allDepartments;
if (function_exists('departments_visible_to_user')) {
    $fromDepartments = departments_visible_to_user($user, $allDepartments);
}
$canChooseAll = can_choose_correspondence_department($user);
$priorityRows = priority_levels_list();
$confidentialityRows = confidentiality_levels_list();
$categories = categories_list();
$error = '';

$postedFrom = (string) ($_POST['from_department_id'] ?? '');
$postedTo = (string) ($_POST['to_department_id'] ?? '');
$primaryId = function_exists('user_primary_department_id')
    ? (int) user_primary_department_id($user)
    : (int) ($user['department_id'] ?? 0);
if ($postedFrom === '' && $primaryId > 0) {
    $postedFrom = (string) $primaryId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $id = save_internal_correspondence($_POST, $_FILES['attachments'] ?? $_FILES['attachment'] ?? null, $user);
        $saved = get_correspondence($id);
        if (!empty($GLOBALS['_dms_corr_cc_warning'])) {
            flash('warning', __('internal.saved') . ' ' . $GLOBALS['_dms_corr_cc_warning']);
        } else {
            if ($saved && function_exists('internal_approval_is_held') && internal_approval_is_held($saved)) {
                $heldStatus = function_exists('outgoing_approval_status_of')
                    ? (string) appr_with('internal', static fn () => outgoing_approval_status_of($saved))
                    : '';
                flash('success', $heldStatus === 'pending_review'
                    ? __('int_issue_appr.saved_to_reviewer')
                    : __('int_issue_appr.saved_held'));
            } else {
                flash('success', __('internal.saved'));
            }
        }
        redirect(base_url('internal/view.php?id=' . $id));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageScripts = dms_page_scripts_with_date_picker($pageScripts ?? '');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('internal.create')) ?></h1>
        <p class="page-sub"><?= e(str_replace(':sample', corr_ref_sample(''), __('internal.numbering_hint'))) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('internal/index.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-building-gear"></i></span>
                <span><?= e(__('internal.create')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php $errorAlertId = 'internalCreateError'; require __DIR__ . '/../includes/corr_form_error.php'; ?>
            <form method="post" enctype="multipart/form-data" class="admin-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/../includes/corr_internal_form_fields.php'; ?>

                <div class="corr-issue-decide">
                    <div class="corr-decide-actions">
                    <button class="btn btn-kff" type="submit" data-keep-label data-confirm="<?= e(function_exists('corr_create_confirm_message') ? corr_create_confirm_message('internal') : __('internal.confirm_save')) ?>"><i class="bi bi-check2 me-1"></i><?= e(__('internal.save')) ?></button>
                    <a href="<?= e(base_url('internal/index.php')) ?>" class="btn btn-kff-soft" data-keep-label data-confirm="<?= e(__('internal.confirm_cancel')) ?>"><?= e(__('action.cancel')) ?></a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
