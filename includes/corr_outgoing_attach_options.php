<?php
/**
 * مرفقات + خيارات الإرسال + المراسل والملاحظات (صادر فردي).
 * اختياري: $attachLabel, $electronicChecked, $rsvpChecked, $notesValue
 */
$attachLabel = $attachLabel ?? __('corr.attach');
$electronicChecked = !empty($electronicChecked);
$rsvpChecked = !empty($rsvpChecked);
$notesValue = (string) ($notesValue ?? '');
$editDocuments = [];
$editDocReturn = '';
$canDeleteEditDocs = false;
$draftFilesLocked = false;
$canReplaceRejectedDraft = false;
if (!empty($corr['id']) && function_exists('get_documents')) {
    $editDocReturn = 'outgoing/edit.php?id=' . (int) $corr['id'];
    foreach (get_documents((int) $corr['id']) as $editDoc) {
        if (str_starts_with((string) ($editDoc['doc_kind'] ?? ''), 'close_')) {
            continue;
        }
        $editDocuments[] = $editDoc;
    }
    $draftEditor = ($corr['type'] ?? '') === 'outgoing'
        && function_exists('outgoing_prep_is_open')
        && outgoing_prep_is_open($corr)
        && !empty($user)
        && user_can('outgoing.edit', $user)
        && function_exists('corr_user_can_edit_in_cycle')
        && corr_user_can_edit_in_cycle($user, $corr);
    $draftFilesLocked = ($corr['type'] ?? '') === 'outgoing'
    && function_exists('outgoing_draft_files_locked')
    && outgoing_draft_files_locked($corr);
if ($draftFilesLocked && function_exists('outgoing_ensure_draft_files_stamped')) {
    outgoing_ensure_draft_files_stamped($corr);
    $editDocuments = [];
    foreach (get_documents((int) $corr['id']) as $editDoc) {
        if (str_starts_with((string) ($editDoc['doc_kind'] ?? ''), 'close_')) {
            continue;
        }
        $editDocuments[] = $editDoc;
    }
}
$canDeleteEditDocs = !$draftFilesLocked && ($draftEditor || (!empty($user) && user_can('documents.delete', $user)));
$canReplaceRejectedDraft = $draftFilesLocked
    && function_exists('outgoing_approval_status_of')
    && outgoing_approval_status_of($corr) === 'rejected'
    && !empty($user)
    && function_exists('corr_user_can_edit_in_cycle')
    && corr_user_can_edit_in_cycle($user, $corr);
}
$showDraftUpload = empty($draftFilesLocked) || !empty($canReplaceRejectedDraft);
if (!isset($dmsEditDocForms)) {
    $dmsEditDocForms = '';
}
?>
<div class="admin-form-section">
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="corr-field-card h-100">
                <?php require __DIR__ . '/corr_attachments_card_title.php'; ?>
                <div class="corr-field-card-body">
                <?php if ($editDocuments): ?>
                    <div class="corr-file-list mb-2">
                        <?php foreach ($editDocuments as $doc): ?>
                            <?php
                            $docId = (int) $doc['id'];
                            $docName = (string) ($doc['file_name'] ?? '');
                            $isPdf = str_contains(strtolower((string) ($doc['file_type'] ?? '')), 'pdf')
                                || str_ends_with(strtolower($docName), '.pdf');
                            $canOpenDoc = empty($user) || user_can_view_corr_document($corr, $doc, $user);
                            $canDeleteThis = $canDeleteEditDocs || !empty($canReplaceRejectedDraft);
                            ?>
                            <div class="corr-file-row">
                                <div class="corr-file-icon"><i class="bi bi-file-earmark<?= $isPdf ? '-pdf' : '' ?>"></i></div>
                                <div class="corr-file-meta">
                                    <?php if ($canOpenDoc): ?>
                                        <a href="<?= e(file_download_url('document', $docId)) ?>" target="_blank"><?= e($docName) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted"><?= e($docName) ?></span>
                                    <?php endif; ?>
                                    <span class="dms-file-list-type ms-1"><?= e(dms_upload_file_type_label($docName, (string) ($doc['file_type'] ?? ''))) ?></span>
                                </div>
                                <?php if ($canDeleteThis): ?>
                                    <button class="btn btn-sm btn-outline-danger" type="submit"
                                            form="delete-doc-<?= $docId ?>"
                                            title="<?= e(__('action.delete')) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php
                                    $dmsEditDocForms .= '<form id="delete-doc-' . $docId . '" method="post" action="'
                                        . e(base_url('documents/delete.php')) . '" data-confirm="'
                                        . e(__('corr.delete_attach_confirm')) . '">'
                                        . csrf_field()
                                        . '<input type="hidden" name="id" value="' . $docId . '">'
                                        . '<input type="hidden" name="return" value="' . e($editDocReturn) . '">'
                                        . '</form>';
                                    ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php
                $showEnclosure = true;
                $attachUploadShowFile = (bool) $showDraftUpload;
                $enclosureOnlyWhenUpload = true;
                $dmsFile = [
                    'name' => 'attachments[]',
                    'id' => 'attachment',
                    'multiple' => true,
                    'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                    'label' => $attachLabel,
                    'hint' => '',
                    'browse' => __('file.browse'),
                    'placeholder' => $editDocuments ? __('file.placeholder_more') : __('file.placeholder'),
                    'selected' => __('file.selected'),
                ];
                require __DIR__ . '/corr_attachments_upload_block.php';
                ?>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="corr-field-card h-100 d-flex flex-column">
                <div class="corr-field-card-title"><i class="bi bi-send me-1"></i><?= e(__('corr.section_options')) ?></div>
                <div class="corr-field-card-body flex-grow-1 d-flex flex-column">
                    <div class="corr-option-grid corr-option-grid-compact corr-option-stack flex-grow-1">
                        <label class="corr-option-card" title="<?= e(__('corr.intend_electronic_hint')) ?>">
                            <input type="checkbox" class="form-check-input" name="intend_electronic" id="intend_electronic" value="1" <?= $electronicChecked ? 'checked' : '' ?>>
                            <span class="corr-option-title"><?= e(__('corr.intend_electronic')) ?></span>
                        </label>
                        <label class="corr-option-card" title="<?= e(__('rsvp.require_hint')) ?>">
                            <input class="form-check-input" type="checkbox" name="rsvp_required" id="rsvp_required" value="1" <?= $rsvpChecked ? 'checked' : '' ?>>
                            <span class="corr-option-title"><?= e(__('rsvp.require')) ?></span>
                        </label>
                    </div>
                    <p class="admin-form-hint mb-0 mt-1 text-truncate d-none" id="entity-email-hint" role="status"></p>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-0">
        <div class="col-lg-6" id="corr-courier-card">
            <?php require __DIR__ . '/corr_courier_field.php'; ?>
        </div>
        <div class="col-lg-6">
            <div class="corr-field-card h-100">
                <div class="corr-field-card-title"><i class="bi bi-journal-text me-1"></i><?= e(__('corr.notes')) ?></div>
                <div class="corr-field-card-body">
                    <label class="visually-hidden" for="notes"><?= e(__('corr.notes')) ?></label>
                    <textarea name="notes" id="notes" class="form-control" rows="1"><?= e($notesValue) ?></textarea>
                </div>
            </div>
        </div>
    </div>
</div>
