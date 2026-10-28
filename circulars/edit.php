<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if (!can_edit_circulars($user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('circulars/index.php'));
}

$id = (int) ($_GET['id'] ?? 0);
$item = get_circular($id);
if (!$item || !user_can_edit_circular($item, $user)) {
    flash('danger', __('circular.not_found'));
    redirect(base_url('circulars/index.php'));
}
$pageTitle = __('circular.edit');
$departments = departments_list();
$allDepartments = $departments;
if (function_exists('departments_visible_to_user')) {
    $departments = departments_visible_to_user($user, $departments);
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        save_circular($_POST, $user, $id);
        if (($_POST['intent'] ?? '') === 'issue') {
            issue_circular($id, $user);
            flash('success', __('circular.issued'));
        } else {
            flash('success', __('circular.updated'));
        }
        redirect(base_url('circulars/view.php?id=' . $id));
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $item = array_merge($item, $_POST);
        $item['id'] = $id;
        $item['status'] = get_circular($id)['status'] ?? $item['status'];
        $item['ref_number'] = get_circular($id)['ref_number'] ?? ($item['ref_number'] ?? '');
    }
}

$pageScripts = dms_page_scripts_with_date_picker($pageScripts ?? '');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('circular.edit')) ?></h1>
        <p class="page-sub" dir="ltr"><?= e((string) $item['ref_number']) ?> — <?= e((string) $item['subject']) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('circulars/view.php?id=' . $id)) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel admin-form-panel is-editing">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-pencil-square"></i></span>
                <span><?= e(__('circular.edit')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <?php if (($item['status'] ?? '') === 'issued'): ?>
                <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i><?= e(__('circular.issued_locked')) ?></div>
            <?php endif; ?>
            <form method="post" class="admin-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/_form_fields.php'; ?>
                <div class="admin-form-section admin-form-section-last">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="corr-field-card h-100">
                                <div class="corr-field-card-title"><i class="bi bi-journal-text me-1"></i><?= e(__('corr.notes')) ?></div>
                                <div class="corr-field-card-body">
                                    <label class="visually-hidden" for="circular_notes"><?= e(__('corr.notes')) ?></label>
                                    <textarea name="notes" id="circular_notes" class="form-control" rows="1"<?= (($item['status'] ?? '') === 'issued') ? ' readonly' : '' ?>><?= e((string) (
                                        $_SERVER['REQUEST_METHOD'] === 'POST'
                                            ? ($_POST['notes'] ?? '')
                                            : ($item['notes'] ?? '')
                                    )) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="admin-form-actions">
                    <button type="submit" name="intent" value="save" class="btn btn-kff">
                        <i class="bi bi-check2 me-1"></i><?= e(__('action.save_changes')) ?>
                    </button>
                    <?php
                    $issueUi = ($item['status'] ?? '') === 'draft' ? dms_perm_ui('circulars.issue', $user) : 'hide';
                    $issueNeedsFile = $issueUi === 'allow' && !circular_is_electronic($item) && !get_circular_files($id);
                    ?>
                    <?php if ($issueNeedsFile): ?>
                    <span class="d-inline-block" tabindex="0" data-bs-toggle="tooltip" title="<?= e(__('circular.issue_need_file')) ?>">
                        <button type="button" class="btn btn-outline-primary" disabled style="pointer-events:none">
                            <i class="bi bi-send me-1"></i><?= e(__('circular.issue')) ?>
                        </button>
                    </span>
                    <?php elseif ($issueUi === 'allow'): ?>
                    <button type="submit" name="intent" value="issue" class="btn btn-outline-primary">
                        <i class="bi bi-send me-1"></i><?= e(__('circular.issue')) ?>
                    </button>
                    <?php elseif ($issueUi === 'deny'): ?>
                    <?= dms_perm_denied_btn(__('circular.issue'), 'bi-send', 'btn-outline-primary') ?>
                    <?php endif; ?>
                    <a href="<?= e(base_url('circulars/view.php?id=' . $id)) ?>" class="btn btn-outline-secondary"><?= e(__('action.cancel')) ?></a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
