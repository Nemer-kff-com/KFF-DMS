<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_login();
if (!user_can_archive_section('general', 'create', $user)) {
    flash('danger', __('auth.forbidden'));
    redirect(base_url('archive/general/index.php'));
}
$pageTitle = __('archive.new');
$departments = departments_list();
if (function_exists('departments_visible_to_user')) {
    $departments = departments_visible_to_user($user, $departments);
}
$error = '';
$includeFiles = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $id = save_archive_item($_POST, $user);
        if (!empty($_FILES['attachment']['name'])) {
            upload_archive_file($_FILES['attachment'], $id, (int) $user['id']);
        }
        if (!empty($_FILES['attachments']['name']) && is_array($_FILES['attachments']['name'])) {
            $count = count($_FILES['attachments']['name']);
            for ($i = 0; $i < $count; $i++) {
                if (($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $one = [
                    'name' => $_FILES['attachments']['name'][$i],
                    'type' => $_FILES['attachments']['type'][$i],
                    'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                    'error' => $_FILES['attachments']['error'][$i],
                    'size' => $_FILES['attachments']['size'][$i],
                ];
                upload_archive_file($one, $id, (int) $user['id']);
            }
        }
        flash('success', __('archive.created'));
        redirect(base_url('archive/general/view.php?id=' . $id));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('archive.create')) ?></h1>
        <p class="page-sub"><?= e(__('archive.create_hint')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('archive/general/index.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-archive"></i></span>
                <span><?= e(__('archive.create')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php $errorAlertId = 'archiveCreateError'; require __DIR__ . '/../../includes/corr_form_error.php'; ?>
            <form method="post" enctype="multipart/form-data" class="admin-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/../../includes/archive_general_form.php'; ?>

                <div class="admin-form-section admin-form-section-last">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="corr-field-card h-100">
                                <div class="corr-field-card-title">
                                    <span><i class="bi bi-paperclip me-1"></i><?= e(__('archive.scan_files')) ?></span>
                                </div>
                                <div class="corr-field-card-body">
                                    <?php
                                    $showEnclosure = false;
                                    $dmsFile = [
                                        'name' => 'attachments[]',
                                        'id' => 'archive_attachments',
                                        'multiple' => true,
                                        'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                                        'label' => __('corr.attach'),
                                        'hint' => '',
                                        'browse' => __('file.browse'),
                                        'placeholder' => __('file.placeholder'),
                                        'selected' => __('file.selected'),
                                    ];
                                    require __DIR__ . '/../../includes/corr_attachments_upload_block.php';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="corr-issue-decide">
                    <div class="corr-decide-actions">
                    <button type="submit" class="btn btn-kff" data-keep-label data-confirm="<?= e(__('archive.confirm_save')) ?>"><i class="bi bi-check2 me-1"></i><?= e(__('action.save')) ?></button>
                    <a href="<?= e(base_url('archive/general/index.php')) ?>" class="btn btn-kff-soft" data-keep-label data-confirm="<?= e(__('archive.confirm_cancel_create')) ?>"><?= e(__('action.cancel')) ?></a>
                    </div>
                </div>
</form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
