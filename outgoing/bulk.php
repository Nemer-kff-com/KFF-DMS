<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if (!user_can('outgoing.bulk', $user)) {
    flash('danger', __('corr.no_perm_add_out'));
    redirect(base_url('outgoing/index.php'));
}

ensure_entity_groups_schema();
$departments = departments_list();
$categories = categories_list();
$groups = entity_groups_list(true, $user);
$priorityRows = priority_levels_list();
$confidentialityRows = confidentiality_levels_list();
$error = '';
$editBatchId = (int) ($_GET['id'] ?? $_POST['batch_id'] ?? 0);
$editBatch = $editBatchId > 0 && function_exists('get_outgoing_batch') ? get_outgoing_batch($editBatchId) : null;
$editRows = [];
$editing = false;
if ($editBatchId > 0) {
    if (!$editBatch || !function_exists('outgoing_batch_draft_editable') || !outgoing_batch_draft_editable($user, $editBatchId)) {
        flash('danger', __('bulk.edit_locked'));
        redirect(base_url($editBatch ? 'outgoing/batch.php?id=' . $editBatchId : 'outgoing/batches.php'));
    }
    $editing = true;
    $editRows = outgoing_batch_draft_rows($editBatchId);
}
$pageTitle = $editing ? __('bulk.edit_title') : __('bulk.title');
$editSource = $editRows[0] ?? [];
$editDelivery = [];
foreach ($editRows as $editRow) {
    $editEntityId = (int) ($editRow['entity_id'] ?? 0);
    if ($editEntityId > 0 && function_exists('correspondence_delivery_mode')) {
        $editDelivery[$editEntityId] = correspondence_delivery_mode($editRow);
    }
}
$preGroup = (int) ($_GET['group'] ?? $_POST['entity_group_id'] ?? 0);
$selectedIds = array_map('intval', $_POST['entity_ids'] ?? []);
if ($editing && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($preGroup === 0) {
        $preGroup = (int) ($editBatch['entity_group_id'] ?? 0);
    }
    $selectedIds = [];
    foreach ($editRows as $editRow) {
        $editEntityId = (int) ($editRow['entity_id'] ?? 0);
        if ($editEntityId > 0) {
            $selectedIds[] = $editEntityId;
        }
    }
}

$groupMembers = [];
foreach ($groups as $g) {
    $groupMembers[(int) $g['id']] = entity_group_member_ids((int) $g['id']);
}
if ($preGroup && !$selectedIds && isset($groupMembers[$preGroup])) {
    $selectedIds = $groupMembers[$preGroup];
}

$selectedPayloads = [];
if ($selectedIds) {
    $selectedRows = entities_list(true, $user, null, ['ids' => $selectedIds, 'limit' => 200]);
    $byId = [];
    foreach ($selectedRows as $row) {
        $byId[(int) $row['id']] = entity_picker_payload($row, false);
    }
    $postedDelivery = is_array($_POST['recipient_delivery'] ?? null) ? $_POST['recipient_delivery'] : [];
    foreach ($selectedIds as $id) {
        if (!isset($byId[$id])) {
            $entity = function_exists('get_entity') ? get_entity($id) : null;
            if (!$entity) {
                continue;
            }
            $byId[$id] = entity_picker_payload($entity, false);
        }
        $payload = $byId[$id];
        $mode = (string) ($postedDelivery[$id] ?? $postedDelivery[(string) $id] ?? ($editDelivery[$id] ?? 'paper'));
        $payload['delivery'] = in_array($mode, ['electronic', 'paper', 'both'], true) ? $mode : 'paper';
        $selectedPayloads[] = $payload;
    }
}

$v = static function (string $key, $default = '') use ($editSource) {
    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }
    if ($editSource !== [] && array_key_exists($key, $editSource) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return $editSource[$key];
    }

    return $default;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postedBatchId = (int) ($_POST['batch_id'] ?? 0);
    try {
        $sendPrep = !empty($_POST['out_prep_submit'])
            && function_exists('outgoing_prep_enabled')
            && outgoing_prep_enabled()
            && function_exists('outgoing_batch_prep_submit');
        if ($postedBatchId > 0) {
            update_outgoing_batch($postedBatchId, $_POST, $_POST['entity_ids'] ?? [], $user, $_FILES['attachments'] ?? $_FILES['attachment'] ?? null);
            $batchId = $postedBatchId;
            if ($sendPrep) {
                $result = outgoing_batch_prep_submit($batchId, $user);
                flash('success', $result === 'converted' ? __('bulk.converted') : __('bulk.submitted'));
                redirect(base_url('outgoing/batch.php?id=' . $batchId));
            }
            flash('success', __('bulk.saved'));
            redirect(base_url('outgoing/bulk.php?id=' . $batchId));
        }
        $batchId = save_outgoing_batch($_POST, $_POST['entity_ids'] ?? [], $user, $_FILES['attachments'] ?? $_FILES['attachment'] ?? null);
        if ($sendPrep) {
            try {
                $result = outgoing_batch_prep_submit($batchId, $user);
                flash('success', $result === 'converted' ? __('bulk.converted') : __('bulk.submitted'));
                redirect(base_url('outgoing/batch.php?id=' . $batchId));
            } catch (Throwable $submitEx) {
                flash('warning', __('out_prep.saved') . ' — ' . $submitEx->getMessage());
                redirect(base_url('outgoing/bulk.php?id=' . $batchId));
            }
        }
        if (function_exists('outgoing_prep_enabled') && outgoing_prep_enabled()) {
            flash('success', __('bulk.created') . ' — ' . __('out_prep.saved'));
            redirect(base_url('outgoing/bulk.php?id=' . $batchId));
        } elseif (function_exists('outgoing_review_enabled') && outgoing_review_enabled()
            && !(function_exists('outgoing_approval_is_configured') && outgoing_approval_is_configured())) {
            flash('success', __('bulk.created') . ' — ' . __('issue_review.requested_ok'));
        } elseif (function_exists('outgoing_approval_is_configured') && outgoing_approval_is_configured()) {
            flash('success', __('bulk.created') . ' — ' . (function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.requested_ok') : __('issue_appr.requested_ok')));
        } else {
            flash('success', __('bulk.created'));
        }
        redirect(base_url('outgoing/batch.php?id=' . $batchId));
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $selectedIds = array_map('intval', $_POST['entity_ids'] ?? []);
    }
}

$pageScripts = dms_page_scripts_with_date_picker($pageScripts ?? '');
require __DIR__ . '/../includes/header.php';
$type = 'outgoing';
$corr = $editRows[0] ?? null;
$prepOn = function_exists('outgoing_prep_enabled') && outgoing_prep_enabled();
$letterTemplateId = (int) (is_array($editBatch) ? ($editBatch['letter_template_id'] ?? 0) : 0);
$prepReturned = $corr && function_exists('outgoing_prep_was_returned') && outgoing_prep_was_returned($corr);
$prepSelf = $prepOn && $corr && function_exists('outgoing_user_is_prep_signer') && outgoing_user_is_prep_signer($user, $corr);

// وثائق الدفعة المحفوظة (من أول مستلم يملك مرفقات)
$editDocuments = [];
$editDocReturn = $editing ? ('outgoing/bulk.php?id=' . $editBatchId) : '';
$dmsEditDocForms = '';
$canDeleteEditDocs = $editing && user_can('outgoing.bulk', $user);
$docsSourceCorr = is_array($corr) ? $corr : null;
if ($editing && function_exists('get_documents')) {
    foreach ($editRows as $editRow) {
        if (!is_array($editRow) || (int) ($editRow['id'] ?? 0) <= 0) {
            continue;
        }
        $rowDocs = [];
        foreach (get_documents((int) $editRow['id']) as $editDoc) {
            if (str_starts_with((string) ($editDoc['doc_kind'] ?? ''), 'close_')) {
                continue;
            }
            $rowDocs[] = $editDoc;
        }
        if ($rowDocs !== []) {
            $editDocuments = $rowDocs;
            $docsSourceCorr = $editRow;
            break;
        }
    }
    if ($docsSourceCorr
        && function_exists('outgoing_draft_files_locked')
        && outgoing_draft_files_locked($docsSourceCorr)
    ) {
        $canDeleteEditDocs = false;
    }
}
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e($editing ? __('bulk.edit_title') : __('bulk.title')) ?></h1>
        <p class="page-sub"><?= e($editing ? __('bulk.edit_sub') : __('bulk.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <?= btn_icon(__('bulk.batches_title'), [
            'href' => base_url('outgoing/batches.php'),
            'icon' => 'bi-collection',
            'class' => 'btn-outline-secondary',
        ]) ?>
        <?= btn_icon(__('nav.entity_groups'), [
            'href' => base_url('admin/entity_groups.php'),
            'icon' => 'bi-people',
            'class' => 'btn-outline-secondary',
        ]) ?>
        <?php if ($editing): ?>
            <?= btn_icon(__('corr.batch_view'), [
                'href' => base_url('outgoing/batch.php?id=' . $editBatchId),
                'icon' => 'bi-eye',
                'class' => 'btn-outline-secondary',
            ]) ?>
        <?php endif; ?>
        <?= dms_back_icon(base_url('outgoing/index.php')) ?>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-send-plus"></i></span>
                <span><?= e($editing ? __('bulk.edit_title') : __('bulk.title')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php require __DIR__ . '/../includes/corr_form_error.php'; ?>
            <?php if ($prepOn): ?>
                <div class="alert alert-info py-2 small no-print">
                    <i class="bi bi-pencil-square me-1"></i>
                    <?= e($editing ? __('bulk.prep_hint') : __('out_prep.create_hint')) ?>
                </div>
            <?php endif; ?>
            <?php if ($prepReturned): ?>
                <div class="alert alert-warning py-2 small no-print">
                    <div class="fw-semibold"><?= e(__('out_prep.returned_note')) ?></div>
                    <div class="mt-1"><?= e(trim((string) ($corr['outgoing_prep_note'] ?? ''))) ?></div>
                </div>
            <?php endif; ?>
            <?php if (!$prepOn && function_exists('outgoing_approval_is_configured') && outgoing_approval_is_configured()): ?>
                <div class="alert alert-info py-2 small no-print">
                    <i class="bi bi-shield-check me-1"></i>
                    <?= e(function_exists('outgoing_appr_text') ? outgoing_appr_text('issue_appr.batch_create_hint') : __('issue_appr.batch_create_hint')) ?>
                </div>
            <?php endif; ?>
            <?php
            $bulkConfirm = __('bulk.confirm');
            if (function_exists('corr_path_destination')) {
                $bulkDest = corr_path_destination('outgoing');
                $bulkConfirm = match ($bulkDest) {
                    'prep' => __('bulk.confirm_prep'),
                    'review' => __('bulk.confirm_review'),
                    'approval' => __('bulk.confirm_approver'),
                    'comms', 'none' => __('bulk.confirm_comms'),
                    default => __('bulk.confirm_comms'),
                };
            }
            ?>
            <form method="post" enctype="multipart/form-data" class="admin-form"<?= $prepOn ? '' : ' data-confirm="' . e($bulkConfirm) . '"' ?>
                  <?php if ($editing && is_array($docsSourceCorr) && (int) ($docsSourceCorr['id'] ?? 0) > 0): ?>
                  data-corr-id="<?= (int) $docsSourceCorr['id'] ?>"
                  data-corr-ref="<?= e((string) ($docsSourceCorr['ref_number'] ?? '')) ?>"
                  <?php elseif ($editing && is_array($corr) && (int) ($corr['id'] ?? 0) > 0): ?>
                  data-corr-id="<?= (int) $corr['id'] ?>"
                  data-corr-ref="<?= e((string) ($corr['ref_number'] ?? '')) ?>"
                  <?php endif; ?>>
                <?= csrf_field() ?>
                <?php if ($editing): ?>
                <input type="hidden" name="batch_id" value="<?= (int) $editBatchId ?>">
                <?php endif; ?>
                <input type="hidden" name="entity_id" id="entity_id" value="<?= !empty($selectedIds[0]) ? (int) $selectedIds[0] : '' ?>">

                <div class="admin-form-section">
                    <div class="admin-form-section-title"><?= e(__('corr.section_basic')) ?></div>
                    <div class="mb-3">
                        <label class="form-label" for="corr_subject"><?= e(__('corr.subject_required')) ?></label>
                        <input type="text" name="subject" id="corr_subject" class="form-control" required value="<?= e((string) $v('subject')) ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 d-flex flex-column gap-3">
                            <div class="corr-field-card corr-sign-card">
                                <div class="corr-field-card-title" title="<?= e(__('corr.issuing_department_hint')) ?>"><i class="bi bi-person-lines-fill me-1"></i><?= e(__('corr.issuing_department')) ?> *</div>
                                <div class="corr-field-card-body">
                                    <?php require __DIR__ . '/../includes/corr_issuing_field.php'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="corr-field-card h-100">
                                <div class="corr-field-card-title"><i class="bi bi-people-fill me-1"></i><?= e(__('bulk.recipients')) ?> *</div>
                                <div class="corr-field-card-body">
                                    <?php
                                    $inputName = 'entity_ids[]';
                                    $pickerId = 'bulk-recipients';
                                    $showDeliveryMode = true;
                                    require __DIR__ . '/../includes/entity_multi_picker.php';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-form-section">
                    <div class="admin-form-section-title"><?= e(__('corr.section_meta')) ?></div>
                    <div class="row g-3 corr-form-details">
                        <div class="col-md-6 col-xl-4">
                            <div class="corr-field-card">
                                <div class="corr-field-card-title"><i class="bi bi-calendar3 me-1"></i><?= e(__('corr.group_dates')) ?></div>
                                <div class="row g-2 corr-field-card-body">
                                    <div class="col-6">
                                        <label class="form-label" for="corr_date-display"><?= e(__('corr.date_out')) ?> *</label>
                                        <?php
                                        render_dms_gregorian_date_field([
                                            'name' => 'corr_date',
                                            'id' => 'corr_date',
                                            'value' => (string) $v('corr_date', date('Y-m-d')),
                                            'required' => true,
                                        ]);
                                        ?>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="reply_due-display"><?= e(__('corr.reply_due')) ?></label>
                                        <?php
                                        render_dms_gregorian_date_field([
                                            'name' => 'reply_due',
                                            'id' => 'reply_due',
                                            'value' => (string) $v('reply_due'),
                                        ]);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-xl-4">
                            <div class="corr-field-card">
                                <div class="corr-field-card-title"><i class="bi bi-shield-lock me-1"></i><?= e(__('corr.group_priority')) ?></div>
                                <div class="row g-2 corr-field-card-body">
                                    <div class="col-6">
                                        <label class="form-label" for="priority"><?= e(__('corr.priority')) ?></label>
                                        <select name="priority" id="priority" class="form-select">
                                            <?php foreach ($priorityRows as $p): ?>
                                                <option value="<?= e($p['code']) ?>" <?= (string) $v('priority', 'normal') === (string) $p['code'] ? 'selected' : '' ?>><?= e(bilingual_label($p)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="confidentiality"><?= e(__('corr.confidentiality')) ?></label>
                                        <div class="input-group conf-with-override">
                                            <select name="confidentiality" id="confidentiality" class="form-select" data-conf-level-select>
                                                <?php foreach ($confidentialityRows as $c): ?>
                                                    <option value="<?= e($c['code']) ?>" <?= (string) $v('confidentiality', 'normal') === (string) $c['code'] ? 'selected' : '' ?>><?= e(bilingual_label($c)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php require __DIR__ . '/../includes/conf_attach_overrides_fields.php'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 col-xl-4">
                            <div class="corr-field-card">
                                <div class="corr-field-card-title"><i class="bi bi-tags me-1"></i><?= e(__('corr.group_classification')) ?></div>
                                <div class="row g-2 corr-field-card-body">
                                    <div class="col-6">
                                        <label class="form-label" for="category_id"><?= e(__('corr.category')) ?></label>
                                        <select name="category_id" id="category_id" class="form-select">
                                            <option value=""><?= e(__('common.none')) ?></option>
                                            <?php foreach ($categories as $c): ?>
                                                <option value="<?= (int) $c['id'] ?>" <?= (string) $v('category_id', $categories ? (string) $categories[0]['id'] : '') === (string) $c['id'] ? 'selected' : '' ?>><?= e(category_display_name($c)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="status"><?= e(__('corr.status')) ?></label>
                                        <input type="hidden" name="status" value="new">
                                        <input type="text" id="status" class="form-control" value="<?= e(status_label('new')) ?>" readonly
                                               title="<?= e(__('corr.status_locked_issue')) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-form-section outgoing-attach-options-section">
                    <div class="row g-2">
                        <div class="col-lg-6">
                            <div class="corr-field-card h-100">
                                <?php require __DIR__ . '/../includes/corr_attachments_card_title.php'; ?>
                                <div class="corr-field-card-body">
                                <?php
                                $dmsFile = [
                                    'name' => 'attachments[]',
                                    'id' => 'attachment',
                                    'multiple' => true,
                                    'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                                    'label' => __('corr.attach'),
                                    'hint' => '',
                                    'browse' => __('file.browse'),
                                    'placeholder' => $editDocuments ? __('file.placeholder_more') : __('file.placeholder'),
                                    'selected' => __('file.selected'),
                                ];
                                require __DIR__ . '/../includes/corr_attachments_upload_block.php';
                                ?>
                                <?php if ($editDocuments): ?>
                                    <div class="corr-file-list mt-3">
                                        <?php foreach ($editDocuments as $doc): ?>
                                            <?php
                                            $docId = (int) ($doc['id'] ?? 0);
                                            $docName = (string) ($doc['file_name'] ?? '');
                                            $isPdf = str_contains(strtolower((string) ($doc['file_type'] ?? '')), 'pdf')
                                                || str_ends_with(strtolower($docName), '.pdf');
                                            $canOpenDoc = empty($user)
                                                || !is_array($docsSourceCorr)
                                                || user_can_view_corr_document($docsSourceCorr, $doc, $user);
                                            ?>
                                            <div class="corr-file-row">
                                                <div class="corr-file-icon"><i class="bi bi-file-earmark<?= $isPdf ? '-pdf' : '' ?>"></i></div>
                                                <div class="corr-file-meta">
                                                    <?php if ($canOpenDoc && $docId > 0): ?>
                                                        <a href="<?= e(file_download_url('document', $docId)) ?>" target="_blank"><?= e($docName) ?></a>
                                                    <?php else: ?>
                                                        <span class="text-muted"><?= e($docName) ?></span>
                                                    <?php endif; ?>
                                                    <span class="dms-file-list-type ms-1"><?= e(dms_upload_file_type_label($docName, (string) ($doc['file_type'] ?? ''))) ?></span>
                                                </div>
                                                <?php if ($canDeleteEditDocs && $docId > 0 && $editDocReturn !== ''): ?>
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
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="corr-field-card h-100 d-flex flex-column">
                                <div class="corr-field-card-title"><i class="bi bi-send me-1"></i><?= e(__('corr.section_options')) ?></div>
                                <div class="corr-field-card-body flex-grow-1 d-flex flex-column">
                                    <div class="corr-option-grid corr-option-grid-compact corr-option-stack flex-grow-1">
                                        <label class="corr-option-card" title="<?= e(__('rsvp.require_hint')) ?>">
                                            <?php
                                            $rsvpChecked = $_SERVER['REQUEST_METHOD'] === 'POST'
                                                ? !empty($_POST['requires_rsvp'])
                                                : ($editing && (!empty($editBatch['requires_rsvp']) || !empty($editSource['rsvp_required'])));
                                            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                                                $rsvpAllowRemote = (string) ($_POST['rsvp_allow_remote'] ?? '1') !== '0';
                                            } elseif (is_array($editBatch) && array_key_exists('rsvp_allow_remote', $editBatch)) {
                                                $rsvpAllowRemote = (int) $editBatch['rsvp_allow_remote'] === 1;
                                            } elseif (array_key_exists('rsvp_allow_remote', $editSource)) {
                                                $rsvpAllowRemote = (int) $editSource['rsvp_allow_remote'] === 1;
                                            } else {
                                                $rsvpAllowRemote = true;
                                            }
                                            ?>
                                            <input class="form-check-input" type="checkbox" name="requires_rsvp" id="requires_rsvp" value="1" <?= $rsvpChecked ? 'checked' : '' ?>>
                                            <span class="corr-option-title"><?= e(__('rsvp.require')) ?></span>
                                        </label>
                                        <div class="rsvp-attend-mode<?= $rsvpChecked ? '' : ' d-none' ?>" id="rsvp-attend-mode">
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="rsvp_allow_remote" id="rsvp_allow_remote_0" value="0" <?= $rsvpAllowRemote ? '' : 'checked' ?><?= $rsvpChecked ? '' : ' disabled' ?>>
                                                <label class="form-check-label" for="rsvp_allow_remote_0"><?= e(__('rsvp.attend_in_person_only')) ?></label>
                                            </div>
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="radio" name="rsvp_allow_remote" id="rsvp_allow_remote_1" value="1" <?= $rsvpAllowRemote ? 'checked' : '' ?><?= $rsvpChecked ? '' : ' disabled' ?>>
                                                <label class="form-check-label" for="rsvp_allow_remote_1"><?= e(__('rsvp.attend_either')) ?></label>
                                            </div>
                                        </div>
                                        <?php
                                        $emailLetterChecked = $_SERVER['REQUEST_METHOD'] === 'POST'
                                            ? !empty($_POST['email_letter_attach'])
                                            : ($editing && !empty($editBatch['email_letter_attach']));
                                        ?>
                                        <label class="corr-option-card" title="<?= e(__('send.email_letter_attach_hint')) ?>">
                                            <input class="form-check-input" type="checkbox" name="email_letter_attach" id="email_letter_attach" value="1" <?= $emailLetterChecked ? 'checked' : '' ?>>
                                            <span class="corr-option-title"><?= e(__('send.email_letter_attach')) ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php $letterSectionAlways = true; require __DIR__ . '/../includes/corr_outgoing_letter_fields.php'; ?>

                <div class="admin-form-section admin-form-section-last">
                    <label class="form-label" for="notes"><?= e(__('corr.notes')) ?></label>
                    <textarea name="notes" id="notes" class="form-control" rows="1"><?= e((string) $v('notes')) ?></textarea>
                </div>

                <div class="corr-issue-decide">
                    <div class="corr-decide-actions">
                    <?php if ($prepOn): ?>
                    <button class="btn btn-kff" type="submit" name="out_prep_submit" value="1" data-keep-label
                            data-confirm="<?= e($prepSelf ? __('out_prep.confirm_submit_self') : __('out_prep.confirm_submit')) ?>">
                        <i class="bi bi-send me-1"></i><?= e($prepSelf
                            ? ($editing ? __('out_prep.save_and_resend_self') : __('out_prep.submit_self'))
                            : (($editing && $prepReturned) ? __('out_prep.save_and_resend') : __('out_prep.save_and_submit'))) ?>
                    </button>
                    <button class="btn btn-outline-secondary" type="submit" data-keep-label
                            data-confirm="<?= e($editing ? __('bulk.confirm_save') : __('bulk.confirm_prep')) ?>">
                        <i class="bi bi-check2 me-1"></i><?= e($editing ? __('action.save_changes') : __('corr.save_draft')) ?>
                    </button>
                    <?php else: ?>
                    <button class="btn btn-kff" type="submit" data-keep-label data-confirm="<?= e($bulkConfirm) ?>">
                        <i class="bi bi-send-plus me-1"></i><?= e(__('bulk.save')) ?>
                    </button>
                    <?php endif; ?>
                    <a class="btn btn-kff-soft" data-keep-label data-confirm="<?= e($editing ? __('bulk.confirm_cancel_edit') : __('bulk.confirm_cancel')) ?>" href="<?= e(base_url($editing ? 'outgoing/batch.php?id=' . $editBatchId : 'outgoing/index.php')) ?>"><?= e(__('action.cancel')) ?></a>
                    </div>
                </div>
            </form>
            <?= $dmsEditDocForms ?>
        </div>
    </div>
</div>
<script>
(function () {
  var form = document.querySelector('form.admin-form');
  var entityId = document.getElementById('entity_id');
  var inputsEl = document.querySelector('[data-bulk-inputs]');
  function syncPreviewEntity() {
    if (!entityId || !inputsEl) return;
    var first = inputsEl.querySelector('input[name="entity_ids[]"]');
    entityId.value = first ? (first.value || '') : '';
  }
  if (inputsEl) {
    var obs = new MutationObserver(syncPreviewEntity);
    obs.observe(inputsEl, { childList: true });
    syncPreviewEntity();
  }
  var rsvpBox = document.getElementById('requires_rsvp');
  var rsvpMode = document.getElementById('rsvp-attend-mode');
  function syncRsvpMode() {
    if (!rsvpBox || !rsvpMode) return;
    var on = rsvpBox.checked;
    rsvpMode.classList.toggle('d-none', !on);
    rsvpMode.querySelectorAll('input[type="radio"]').forEach(function (input) {
      input.disabled = !on;
    });
  }
  if (rsvpBox) {
    rsvpBox.addEventListener('change', syncRsvpMode);
    syncRsvpMode();
  }
  if (form && !form.dataset.invalidBound) {
    form.dataset.invalidBound = '1';
    form.addEventListener('invalid', function (e) {
      var field = e.target;
      if (!field || window.__dmsInvalidShown) return;
      window.__dmsInvalidShown = true;
      window.setTimeout(function () { window.__dmsInvalidShown = false; }, 500);
      var msg = field.validationMessage || '';
      if (window.Swal && msg) {
        Swal.fire({ icon: 'warning', text: msg, confirmButtonColor: '#3B4F6E' });
      }
    }, true);
  }
})();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
