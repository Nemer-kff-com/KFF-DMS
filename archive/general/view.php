<?php
require_once __DIR__ . '/../../config/auth.php';
$user = require_archive_section('general');
$id = (int) ($_GET['id'] ?? 0);
$item = get_archive_item($id);
if (!$item || !user_can_access_archive($item, $user)) {
    flash('danger', __('archive.not_found'));
    redirect(base_url('archive/general/index.php'));
}
$files = get_archive_files($id);
$canEdit = user_can_edit_archive($item, $user);
$canDeleteItem = user_can_archive_section('general', 'delete', $user) && user_can_access_archive($item, $user);
$editDenied = dms_perm_ui(archive_section_perm('general', 'edit'), $user) === 'deny';
$deleteDenied = dms_perm_ui(archive_section_perm('general', 'delete'), $user) === 'deny';
$pageTitle = archive_title($item);
$activity = get_archive_activity($id, 40, $item);
$archiveNo = trim((string) ($item['archive_no'] ?? ''));
$locationLabel = archive_location_label($item);

$linkedType = (string) ($item['linked_type'] ?? '');
$linkedFolder = match ($linkedType) {
    'outgoing' => 'outgoing',
    'internal' => 'internal',
    default => 'incoming',
};

require __DIR__ . '/../../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(archive_title($item)) ?></h1>
        <p class="page-sub">
            <?php if ($archiveNo !== ''): ?>
                <span class="fw-semibold me-2" dir="ltr"><?= e($archiveNo) ?></span>
            <?php endif; ?>
            <span class="badge text-bg-light border"><?= e(archive_doc_type_label($item['doc_type'])) ?></span>
            <?php if (!empty($item['old_ref'])): ?>
                <span class="ms-2 text-muted" dir="ltr"><?= e((string) $item['old_ref']) ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('archive/general/index.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('archive/general/print.php?id=' . $id)) ?>" target="_blank" rel="noopener">
            <i class="bi bi-printer me-1"></i><?= e(__('action.print')) ?>
        </a>
        <?php if ($canEdit): ?>
            <a class="btn btn-outline-primary" href="<?= e(base_url('archive/general/edit.php?id=' . $id)) ?>"><?= e(__('action.edit')) ?></a>
        <?php elseif ($editDenied): ?>
            <?= dms_perm_denied_btn(__('action.edit'), '', 'btn-outline-primary') ?>
        <?php endif; ?>
        <?php if ($canDeleteItem): ?>
            <form method="post" action="<?= e(base_url('archive/general/delete.php')) ?>" class="d-inline" data-confirm="<?= e(__('archive.confirm_delete')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-outline-danger"><?= e(__('action.delete')) ?></button>
            </form>
        <?php elseif ($deleteDenied): ?>
            <?= dms_perm_denied_btn(__('action.delete'), '', 'btn-outline-danger') ?>
        <?php endif; ?>
    </div>
</div>

<div class="page-narrow">
<div class="row g-4 corr-view">
    <div class="col-xl-8">
    <div class="card-panel mb-3">
        <div class="card-header"><i class="bi bi-info-circle me-1"></i><?= e(__('archive.details')) ?></div>
        <div class="card-body p-0">
            <div class="corr-meta-grid">
                <?php if ($archiveNo !== ''): ?>
                <div class="corr-meta-item">
                    <span class="corr-meta-label"><?= e(__('archive.number')) ?></span>
                    <span class="corr-meta-value" dir="ltr"><?= e($archiveNo) ?></span>
                </div>
                <?php endif; ?>
                <?php if (trim((string) ($item['title_en'] ?? '')) !== ''): ?>
                <div class="corr-meta-item corr-meta-span-2">
                    <span class="corr-meta-label"><?= e(__('archive.doc_title_en')) ?></span>
                    <span class="corr-meta-value" dir="ltr"><?= e((string) $item['title_en']) ?></span>
                </div>
                <?php endif; ?>
                <div class="corr-meta-item">
                    <span class="corr-meta-label"><?= e(__('archive.doc_type')) ?></span>
                    <span class="corr-meta-value"><?= e(archive_doc_type_label($item['doc_type'])) ?></span>
                </div>
                <div class="corr-meta-item">
                    <span class="corr-meta-label"><?= e(__('corr.category')) ?></span>
                    <span class="corr-meta-value"><?= e(category_display_name(['name' => $item['category_name'] ?? '', 'name_en' => $item['category_name_en'] ?? '']) ?: __('common.none')) ?></span>
                </div>
                <div class="corr-meta-item">
                    <span class="corr-meta-label"><?= e(__('archive.doc_date')) ?></span>
                    <span class="corr-meta-value"><?= e(format_date($item['doc_date'])) ?></span>
                </div>
                <?php if (trim((string) ($item['doc_year'] ?? '')) !== ''): ?>
                <div class="corr-meta-item">
                    <span class="corr-meta-label"><?= e(__('archive.doc_year')) ?></span>
                    <span class="corr-meta-value" dir="ltr"><?= e((string) $item['doc_year']) ?></span>
                </div>
                <?php endif; ?>
                <div class="corr-meta-item">
                    <span class="corr-meta-label"><?= e(__('archive.old_ref')) ?></span>
                    <span class="corr-meta-value">
                        <?php $oldRef = trim((string) ($item['old_ref'] ?? '')); ?>
                        <?php if ($oldRef !== ''): ?>
                            <span dir="ltr"><?= e($oldRef) ?></span>
                        <?php else: ?>
                            <?= e(__('common.none')) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="corr-meta-item corr-meta-span-2">
                    <span class="corr-meta-label"><?= e(__('archive.party')) ?></span>
                    <span class="corr-meta-value"><?= e($item['party_name'] ?? __('common.none')) ?></span>
                </div>
                <?php if ($locationLabel !== ''): ?>
                <div class="corr-meta-item corr-meta-span-2">
                    <span class="corr-meta-label"><?= e(__('archive.location')) ?></span>
                    <span class="corr-meta-value"><?= e($locationLabel) ?></span>
                </div>
                <?php endif; ?>
                <div class="corr-meta-item">
                    <span class="corr-meta-label"><?= e(__('corr.department')) ?></span>
                    <span class="corr-meta-value"><?= e(department_display_name($item, 'department_name', 'department_name_en') ?: __('common.none')) ?></span>
                </div>
                <div class="corr-meta-item">
                    <span class="corr-meta-label"><?= e(__('corr.confidentiality')) ?></span>
                    <span class="corr-meta-value"><?= e(confidentiality_label($item['confidentiality'])) ?></span>
                </div>
                <?php foreach (archive_extra_display_rows($item) as $xfRow): ?>
                <div class="corr-meta-item corr-meta-span-2">
                    <span class="corr-meta-label"><?= e($xfRow['label']) ?></span>
                    <span class="corr-meta-value"><?= nl2br(e($xfRow['value'])) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (!empty($item['keywords'])): ?>
                <div class="corr-meta-item corr-meta-span-2">
                    <span class="corr-meta-label"><?= e(__('archive.keywords')) ?></span>
                    <span class="corr-meta-value"><?= e((string) $item['keywords']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['notes'])): ?>
                <div class="corr-meta-item corr-meta-span-full">
                    <span class="corr-meta-label"><?= e(__('corr.notes')) ?></span>
                    <span class="corr-meta-value"><?= nl2br(e((string) $item['notes'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($item['correspondence_id'])): ?>
                <div class="corr-meta-item corr-meta-span-2">
                    <span class="corr-meta-label"><?= e(__('archive.linked_corr')) ?></span>
                    <span class="corr-meta-value">
                        <a href="<?= e(base_url($linkedFolder . '/view.php?id=' . (int) $item['correspondence_id'])) ?>">
                            <?= e($item['linked_ref'] ?? ('#' . $item['correspondence_id'])) ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card-panel">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-paperclip me-1"></i><?= e(__('archive.files')) ?></span>
            <span class="badge text-bg-light text-dark"><?= count($files) ?></span>
        </div>
        <div class="card-body p-4">
            <?php if (!$files): ?>
                <p class="text-muted <?= $canEdit ? 'mb-3' : 'mb-0' ?>"><?= e(__('archive.no_files')) ?></p>
            <?php else: ?>
                <div class="corr-file-list<?= $canEdit ? ' mb-3' : '' ?>">
                    <?php foreach ($files as $f): ?>
                        <?php
                        $fileName = archive_item_file_display_name($item, $f, $files);
                        $isPdf = str_contains(strtolower((string) ($f['file_type'] ?? '')), 'pdf')
                            || str_ends_with(strtolower($fileName), '.pdf');
                        $canOpen = user_can_view_archive_file($item, $f, $user);
                        $openUrl = file_download_url('archive', (int) $f['id']);
                        ?>
                        <div class="corr-file-row">
                            <div class="corr-file-icon"><i class="bi bi-file-earmark<?= $isPdf ? '-pdf' : '' ?>"></i></div>
                            <div class="corr-file-meta">
                                <?php if ($canOpen): ?>
                                    <a href="<?= e($openUrl) ?>" target="_blank" rel="noopener" dir="ltr"><?= e($fileName) ?></a>
                                <?php else: ?>
                                    <span class="text-muted" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('conf.archive_file_locked')) ?>">
                                        <i class="bi bi-lock-fill me-1"></i><span dir="ltr"><?= e($fileName) ?></span>
                                    </span>
                                <?php endif; ?>
                                <span class="dms-file-list-type ms-1"><?= e(dms_upload_file_type_label($fileName, (string) ($f['file_type'] ?? ''))) ?></span>
                                <div class="small text-muted">
                                    <?= e(user_display_name($f, 'uploader_name', 'uploader_name_en') ?: __('common.none')) ?>
                                    — <?= e(format_datetime($f['uploaded_at'])) ?>
                                    — <?= e(number_format(((int) $f['file_size']) / 1024, 1)) ?> KB
                                </div>
                            </div>
                            <?php if ($canDeleteItem): ?>
                                <form method="post" action="<?= e(base_url('archive/general/file_delete.php')) ?>" class="d-inline" data-confirm="<?= e(__('corr.delete_attach_confirm')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                    <input type="hidden" name="archive_id" value="<?= $id ?>">
                                    <button class="btn btn-sm btn-icon btn-outline-danger" type="submit" title="<?= e(__('action.delete')) ?>" aria-label="<?= e(__('action.delete')) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($canEdit): ?>
            <form method="post" enctype="multipart/form-data" action="<?= e(base_url('archive/general/upload.php')) ?>" class="no-print mt-2">
                <?= csrf_field() ?>
                <input type="hidden" name="archive_id" value="<?= $id ?>">
                <?php
                $showEnclosure = false;
                $dmsFile = [
                    'name' => 'attachments[]',
                    'id' => 'archive_upload_files',
                    'multiple' => true,
                    'required' => false,
                    'autosubmit' => true,
                    'show_list' => false,
                    'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                    'label' => $files ? __('corr.add_attach') : __('corr.attach'),
                    'hint' => '',
                    'browse' => __('file.browse'),
                    'placeholder' => $files ? __('file.placeholder_more') : __('file.placeholder'),
                    'selected' => __('file.selected'),
                ];
                require __DIR__ . '/../../includes/corr_attachments_upload_block.php';
                ?>
            </form>
            <?php endif; ?>
        </div>
    </div>
    </div>
    <div class="col-xl-4">
        <div class="card-panel mb-3">
            <div class="card-header"><i class="bi bi-journal-text me-1"></i><?= e(__('corr.activity_title')) ?></div>
            <div class="card-body p-3">
                <?php if (!$activity): ?>
                    <p class="text-muted small mb-0"><?= e(__('corr.activity_empty')) ?></p>
                <?php else: ?>
                    <ul class="list-unstyled corr-activity mb-0">
                        <?php foreach ($activity as $ev): ?>
                            <li class="corr-activity-item">
                                <div class="d-flex justify-content-between gap-2">
                                    <strong class="small"><?= e(activity_action_label((string) ($ev['action'] ?? ''))) ?></strong>
                                    <span class="small text-muted text-nowrap"><?= e(format_datetime($ev['created_at'] ?? null)) ?></span>
                                </div>
                                <div class="small text-muted"><?= e(user_display_name($ev, 'user_name', 'user_name_en') ?: __('common.none')) ?></div>
                                <?php
                                $activityDetails = activity_details_for_display(
                                    (string) ($ev['action'] ?? ''),
                                    (string) ($ev['details'] ?? '')
                                );
                                ?>
                                <?php if ($activityDetails !== ''): ?>
                                    <div class="small"><?= e($activityDetails) ?></div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
