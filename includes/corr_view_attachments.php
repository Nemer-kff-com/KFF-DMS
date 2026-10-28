<?php
if (($corr['type'] ?? '') === 'outgoing' && function_exists('correspondence_align_outgoing_attachment_names')) {
    correspondence_align_outgoing_attachment_names((int) $corr['id']);
    $documents = get_documents((int) $corr['id']);
}
$draftFilesLocked = ($corr['type'] ?? '') === 'outgoing'
    && function_exists('outgoing_draft_files_locked')
    && outgoing_draft_files_locked($corr);
if ($draftFilesLocked && function_exists('outgoing_ensure_draft_files_stamped')) {
    outgoing_ensure_draft_files_stamped($corr);
    $documents = get_documents((int) $corr['id']);
}
usort($documents, static function (array $a, array $b): int {
    return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
});
$issuedKind = function_exists('outgoing_issued_copy_kind') ? outgoing_issued_copy_kind() : 'issued_copy';
$draftKind = function_exists('outgoing_draft_file_kind') ? outgoing_draft_file_kind() : 'draft_approved';
$needsIssuedCopy = function_exists('outgoing_needs_issued_copy') && outgoing_needs_issued_copy($corr);
$issuedMutable = $needsIssuedCopy && function_exists('outgoing_issued_copy_mutable') && outgoing_issued_copy_mutable($corr);
$issuedCopyTip = __('out_prep.issued_copy_hint');
$canManageIssued = $issuedMutable
    && function_exists('outgoing_user_can_manage_issued_copy')
    && outgoing_user_can_manage_issued_copy($user, $corr)
    && $uploadUi === 'allow';
$draftDocs = [];
$issuedDocs = [];
$otherDocs = [];
if ($draftFilesLocked) {
    foreach ($documents as $doc) {
        $kind = (string) ($doc['doc_kind'] ?? '');
        if ($kind === $issuedKind) {
            $issuedDocs[] = $doc;
        } elseif ($kind === $draftKind || $kind === '') {
            $draftDocs[] = $doc;
        } else {
            $otherDocs[] = $doc;
        }
    }
}
$renderDocRow = static function (array $doc, bool $allowDelete) use ($corr, $user, $typeFolder): void {
    $isPdf = str_contains(strtolower((string) ($doc['file_type'] ?? '')), 'pdf')
        || str_ends_with(strtolower((string) ($doc['file_name'] ?? '')), '.pdf');
    ?>
    <div class="corr-file-row">
        <div class="corr-file-icon"><i class="bi bi-file-earmark<?= $isPdf ? '-pdf' : '' ?>"></i></div>
        <div class="corr-file-meta">
            <?php if (user_can_view_corr_document($corr, $doc, $user)): ?>
            <a class="corr-file-name" href="<?= e(file_download_url('document', (int) $doc['id'])) ?>" target="_blank"><?= e($doc['file_name']) ?></a>
            <?php else: ?>
            <span class="text-muted" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('conf.file_locked')) ?>"><i class="bi bi-lock-fill me-1"></i><span class="corr-file-name"><?= e($doc['file_name']) ?></span></span>
            <?php endif; ?>
            <span class="dms-file-list-type ms-1"><?= e(dms_upload_file_type_label((string) ($doc['file_name'] ?? ''), (string) ($doc['file_type'] ?? ''))) ?></span>
            <div class="small text-muted"><?= e(user_display_name($doc, 'uploader_name', 'uploader_name_en')) ?> — <?= e(format_datetime($doc['uploaded_at'])) ?></div>
        </div>
        <?php if ($allowDelete): ?>
        <form method="post" action="<?= e(base_url('documents/delete.php')) ?>" data-confirm="<?= e(__('corr.delete_attach_confirm')) ?>" class="no-print">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
            <input type="hidden" name="return" value="<?= e($typeFolder.'/view.php?id='.(int)$corr['id']) ?>">
            <button class="btn btn-sm btn-outline-danger" type="submit"><?= e(__('action.delete')) ?></button>
        </form>
        <?php endif; ?>
    </div>
    <?php
};
?>
        <div class="card-panel mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-paperclip me-1"></i><?= e(__('corr.attachments')) ?></span>
                <span class="badge text-bg-light text-dark"><?= count($documents) ?></span>
            </div>
            <div class="card-body p-4">
                <?php if ($draftFilesLocked): ?>
                    <?php $showIssuedCol = $needsIssuedCopy || $issuedDocs !== []; ?>
                    <div class="row g-3 align-items-start">
                    <div class="<?= $showIssuedCol ? 'col-md-6' : 'col-12' ?>">
                        <div class="fw-semibold mb-1 d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-tip-edge="start" title="<?= e(__('out_prep.draft_files_locked')) ?>"><?= e(__('out_prep.draft_files_title')) ?></div>
                        <?php if (!$draftDocs): ?>
                            <p class="text-muted small mb-0"><?= e(__('corr.no_attachments')) ?></p>
                        <?php else: ?>
                            <div class="corr-file-list">
                                <?php foreach ($draftDocs as $doc) { $renderDocRow($doc, false); } ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($showIssuedCol): ?>
                    <div class="col-md-6">
                        <div class="fw-semibold mb-1 d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-tip-edge="start" title="<?= e($issuedCopyTip) ?>"><?= e(__('out_prep.issued_copy_title')) ?></div>
                        <?php if (!$issuedDocs): ?>
                            <div class="alert alert-warning py-2 small"><?= e(__('out_prep.issued_copy_missing')) ?></div>
                        <?php else: ?>
                            <div class="corr-file-list mb-3">
                                <?php foreach ($issuedDocs as $doc) { $renderDocRow($doc, $canManageIssued); } ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($canManageIssued): ?>
                        <form method="post" enctype="multipart/form-data" class="corr-upload-bar no-print" action="<?= e(base_url('documents/upload.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="correspondence_id" value="<?= (int)$corr['id'] ?>">
                            <input type="hidden" name="doc_kind" value="<?= e($issuedKind) ?>">
                            <input type="hidden" name="return" value="<?= e($typeFolder.'/view.php?id='.(int)$corr['id']) ?>">
                            <?php
                            $dmsFile = [
                                'name' => 'attachments[]',
                                'id' => 'corr_issued_copy',
                                'multiple' => true,
                                'required' => false,
                                'autosubmit' => true,
                                'show_list' => false,
                                'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                                'label' => __('out_prep.issued_copy_upload'),
                                'browse' => __('file.browse'),
                                'placeholder' => __('file.placeholder'),
                                'selected' => __('file.selected'),
                            ];
                            require __DIR__ . '/dms_file_field.php';
                            ?>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    </div>
                    <?php if ($otherDocs): ?>
                        <div class="corr-file-list">
                            <?php foreach ($otherDocs as $doc) { $renderDocRow($doc, false); } ?>
                        </div>
                    <?php endif; ?>
                <?php elseif ($needsIssuedCopy): ?>
                    <?php
                    $plainOther = [];
                    $plainIssued = [];
                    foreach ($documents as $doc) {
                        if ((string) ($doc['doc_kind'] ?? '') === $issuedKind) {
                            $plainIssued[] = $doc;
                        } else {
                            $plainOther[] = $doc;
                        }
                    }
                    ?>
                    <div class="row g-3 align-items-start">
                    <div class="col-md-6">
                        <div class="fw-semibold mb-1 d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-tip-edge="start" title="<?= e(__('out_prep.reviewed_files_locked')) ?>"><?= e(__('out_prep.reviewed_files_title')) ?></div>
                        <?php if (!$plainOther): ?>
                            <p class="text-muted small mb-0"><?= e(__('corr.no_attachments')) ?></p>
                        <?php else: ?>
                            <div class="corr-file-list">
                                <?php foreach ($plainOther as $doc) { $renderDocRow($doc, false); } ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-semibold mb-1 d-inline-block" tabindex="0" data-bs-toggle="tooltip" data-tip-edge="start" title="<?= e($issuedCopyTip) ?>"><?= e(__('out_prep.issued_copy_title')) ?></div>
                        <?php if (!$plainIssued): ?>
                            <div class="alert alert-warning py-2 small"><?= e(__('out_prep.issued_copy_missing')) ?></div>
                        <?php else: ?>
                            <div class="corr-file-list mb-3">
                                <?php foreach ($plainIssued as $doc) { $renderDocRow($doc, $canManageIssued); } ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($canManageIssued): ?>
                        <form method="post" enctype="multipart/form-data" class="corr-upload-bar no-print" action="<?= e(base_url('documents/upload.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="correspondence_id" value="<?= (int)$corr['id'] ?>">
                            <input type="hidden" name="doc_kind" value="<?= e($issuedKind) ?>">
                            <input type="hidden" name="return" value="<?= e($typeFolder.'/view.php?id='.(int)$corr['id']) ?>">
                            <?php
                            $dmsFile = [
                                'name' => 'attachments[]',
                                'id' => 'corr_issued_copy',
                                'multiple' => true,
                                'required' => false,
                                'autosubmit' => true,
                                'show_list' => false,
                                'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                                'label' => __('out_prep.issued_copy_upload'),
                                'browse' => __('file.browse'),
                                'placeholder' => __('file.placeholder'),
                                'selected' => __('file.selected'),
                            ];
                            require __DIR__ . '/dms_file_field.php';
                            ?>
                        </form>
                        <?php endif; ?>
                    </div>
                    </div>
                <?php elseif (!$documents): ?>
                    <div class="<?= $isIncoming ? 'alert alert-warning mb-3 no-print' : 'text-muted mb-3' ?>" role="status">
                        <?php if ($isIncoming): ?><i class="bi bi-exclamation-triangle me-1"></i><?php endif; ?>
                        <?= e($isIncoming ? __('corr.scan_missing') : __('corr.no_attachments')) ?>
                    </div>
                <?php else: ?>
                    <div class="corr-file-list<?= $canUploadDocs ? ' mb-3' : '' ?>">
                        <?php foreach ($documents as $doc): ?>
                            <div class="corr-file-row">
                                <div class="corr-file-icon"><i class="bi bi-file-earmark<?= str_contains(strtolower((string) ($doc['file_type'] ?? '')), 'pdf') || str_ends_with(strtolower((string) ($doc['file_name'] ?? '')), '.pdf') ? '-pdf' : '' ?>"></i></div>
                                <div class="corr-file-meta">
                                    <?php if (user_can_view_corr_document($corr, $doc, $user)): ?>
                                    <a class="corr-file-name" href="<?= e(file_download_url('document', (int) $doc['id'])) ?>" target="_blank"><?= e($doc['file_name']) ?></a>
                                    <?php else: ?>
                                    <span class="text-muted" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('conf.file_locked')) ?>"><i class="bi bi-lock-fill me-1"></i><span class="corr-file-name"><?= e($doc['file_name']) ?></span></span>
                                    <?php endif; ?>
                                    <span class="dms-file-list-type ms-1"><?= e(dms_upload_file_type_label((string) ($doc['file_name'] ?? ''), (string) ($doc['file_type'] ?? ''))) ?></span>
                                    <?php
                                    $docKind = (string) ($doc['doc_kind'] ?? '');
                                    if ($docKind !== '' && function_exists('correspondence_close_doc_kind_label')):
                                    ?>
                                        <span class="badge text-bg-light text-dark ms-1"><?= e(correspondence_close_doc_kind_label($docKind)) ?></span>
                                    <?php endif; ?>
                                    <div class="small text-muted"><?= e(user_display_name($doc, 'uploader_name', 'uploader_name_en')) ?> — <?= e(format_datetime($doc['uploaded_at'])) ?></div>
                                </div>
                                <?php if ($canDeleteDocs): ?>
                                <form method="post" action="<?= e(base_url('documents/delete.php')) ?>" data-confirm="<?= e(__('corr.delete_attach_confirm')) ?>" class="no-print">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$doc['id'] ?>">
                                    <input type="hidden" name="return" value="<?= e($typeFolder.'/view.php?id='.(int)$corr['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" type="submit"><?= e(__('action.delete')) ?></button>
                                </form>
                                <?php elseif ($deleteDocDenied): ?>
                                <span class="no-print"><?= dms_perm_denied_btn(__('action.delete'), '', 'btn-sm btn-outline-danger') ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($canUploadDocs && !$draftFilesLocked && !$needsIssuedCopy): ?>
                <form method="post" enctype="multipart/form-data" class="corr-upload-bar no-print mt-2" action="<?= e(base_url('documents/upload.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="correspondence_id" value="<?= (int)$corr['id'] ?>">
                    <input type="hidden" name="return" value="<?= e($typeFolder.'/view.php?id='.(int)$corr['id']) ?>">
                    <?php
                    $enclosureStartEmpty = true;
                    $dmsFile = [
                        'name' => 'attachments[]',
                        'id' => 'corr_upload_attachment',
                        'multiple' => true,
                        'required' => false,
                        'autosubmit' => true,
                        'show_list' => false,
                        'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                        'label' => $documents ? __('corr.add_attach') : __('corr.attach'),
                        'hint' => '',
                        'browse' => __('file.browse'),
                        'placeholder' => $documents ? __('file.placeholder_more') : __('file.placeholder'),
                        'selected' => __('file.selected'),
                    ];
                    require __DIR__ . '/corr_attachments_upload_block.php';
                    ?>
                </form>
                <?php elseif ($uploadDenied && !$draftFilesLocked && !$needsIssuedCopy): ?>
                <div class="no-print<?= $documents ? '' : ' mt-2' ?>">
                    <?= dms_perm_denied_btn($documents ? __('corr.add_attach') : __('file.browse'), 'bi-paperclip', 'btn-outline-secondary') ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

