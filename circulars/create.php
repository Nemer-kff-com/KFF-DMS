<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
if (!user_can('circulars.create', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('circulars/index.php'));
}
$pageTitle = __('circular.new');
$departments = departments_list();
$allDepartments = $departments;
if (function_exists('departments_visible_to_user')) {
    $departments = departments_visible_to_user($user, $departments);
}
$error = '';
$item = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $savedId = 0;
    try {
        $savedId = save_circular($_POST, $user);
        circular_upload_from_request($savedId, (int) $user['id']);
        if (($_POST['intent'] ?? '') === 'issue') {
            issue_circular($savedId, $user);
            flash('success', __('circular.issued'));
        } else {
            flash('success', __('circular.created'));
        }
        redirect(base_url('circulars/view.php?id=' . $savedId));
    } catch (Throwable $e) {
        if ($savedId > 0) {
            flash('danger', $e->getMessage());
            redirect(base_url('circulars/view.php?id=' . $savedId));
        }
        $error = $e->getMessage();
    }
}

$pageScripts = dms_page_scripts_with_date_picker($pageScripts ?? '');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('circular.create')) ?></h1>
        <p class="page-sub"><?= e(__('circular.create_hint')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('circulars/index.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-megaphone"></i></span>
                <span><?= e(__('circular.create')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="admin-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/_form_fields.php'; ?>
                <?php
                $circularElectronic = !empty($circularElectronic);
                $circularFileTitle = $circularElectronic ? __('circular.annex_files') : __('circular.scan_files');
                $circularFileHint = $circularElectronic ? __('circular.annex_hint') : __('circular.scan_hint');
                ?>
                <div class="admin-form-section admin-form-section-last" id="circular-file-section">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="corr-field-card h-100">
                                <div class="corr-field-card-title corr-attachments-card-title">
                                    <span id="circular-file-title"
                                          data-file="<?= e(__('circular.scan_files')) ?>"
                                          data-electronic="<?= e(__('circular.annex_files')) ?>">
                                        <i class="bi bi-paperclip me-1"></i><?= e($circularFileTitle) ?>
                                    </span>
                                </div>
                                <div class="corr-field-card-body">
                                    <p class="admin-form-hint mb-2" id="circular-file-hint"
                                       data-file="<?= e(__('circular.scan_hint')) ?>"
                                       data-electronic="<?= e(__('circular.annex_hint')) ?>"><?= e($circularFileHint) ?></p>
                                    <?php
                                    $showEnclosure = false;
                                    $dmsFile = [
                                        'name' => 'attachments[]',
                                        'id' => 'circular_attachments',
                                        'multiple' => true,
                                        'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                                        'label' => '',
                                        'hint' => '',
                                        'browse' => __('file.browse'),
                                        'placeholder' => __('file.placeholder'),
                                        'selected' => __('file.selected'),
                                    ];
                                    require __DIR__ . '/../includes/corr_attachments_upload_block.php';
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="corr-field-card h-100">
                                <div class="corr-field-card-title"><i class="bi bi-journal-text me-1"></i><?= e(__('corr.notes')) ?></div>
                                <div class="corr-field-card-body">
                                    <label class="visually-hidden" for="circular_notes"><?= e(__('corr.notes')) ?></label>
                                    <textarea name="notes" id="circular_notes" class="form-control" rows="1"><?= e((string) ($_POST['notes'] ?? '')) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="corr-issue-decide">
                    <div class="corr-decide-actions">
                    <button type="submit" name="intent" value="save" class="btn btn-kff-soft" data-keep-label data-confirm="<?= e(__('circular.confirm_save_draft')) ?>">
                        <i class="bi bi-check2 me-1"></i><?= e(__('circular.save_draft')) ?>
                    </button>
                    <?php $issueUi = dms_perm_ui('circulars.issue', $user); ?>
                    <?php if ($issueUi === 'allow'): ?>
                    <?php $issueNeedsFile = empty($circularElectronic); ?>
                    <span class="d-inline-block" id="circular_issue_wrap" data-file-tip="<?= e(__('circular.issue_need_file')) ?>"<?= $issueNeedsFile ? ' tabindex="0" data-bs-toggle="tooltip" title="' . e(__('circular.issue_need_file')) . '"' : '' ?>>
                    <button type="submit" name="intent" value="issue" id="circular_issue_btn" class="btn btn-kff" data-keep-label data-confirm="<?= e(__('circular.confirm_issue')) ?>"<?= $issueNeedsFile ? ' disabled style="pointer-events:none"' : '' ?>>
                        <i class="bi bi-send me-1"></i><?= e(__('circular.issue')) ?>
                    </button>
                    </span>
                    <?php elseif ($issueUi === 'deny'): ?>
                    <?= dms_perm_denied_btn(__('circular.issue'), 'bi-send', 'btn-kff', '') ?>
                    <?php endif; ?>
                    <a href="<?= e(base_url('circulars/index.php')) ?>" class="btn btn-kff-soft" data-keep-label data-confirm="<?= e(__('circular.confirm_cancel')) ?>"><?= e(__('action.cancel')) ?></a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
