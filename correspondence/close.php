<?php
require_once __DIR__ . '/../config/auth.php';
$user = require_login();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$corr = get_correspondence($id);
if (!$corr || !user_can_access_correspondence($corr, $user)) {
    flash('danger', __('corr.not_found'));
    redirect(base_url('index.php'));
}

$folder = correspondence_folder_for_type((string) ($corr['type'] ?? 'incoming'));
$viewUrl = $folder . '/view.php?id=' . $id;
$isCancelled = correspondence_is_cancelled($corr);
$isClosed = correspondence_is_closed($corr);
$closeReq = (!$isClosed && !$isCancelled && close_request_applies($corr)) ? close_request_pending($id) : null;
$canDirect = $isClosed || close_user_can_close_direct($user, $corr);
// direct = إقفال مباشر، request = طلب إقفال، pending = طلب قائم بانتظار صاحب القرار، view = عرض فقط
$mode = !(user_can('correspondence.close', $user) && !$isCancelled) ? 'view' : ($canDirect ? 'direct' : ($closeReq ? 'pending' : 'request'));
$canEdit = in_array($mode, ['direct', 'request'], true);
$isRequestMode = $mode === 'request';
$needAdminReason = $mode === 'direct' && !$isClosed && close_user_needs_admin_reason($user, $corr);
$deciderDeptName = close_request_applies($corr) ? close_department_name(close_decider_department_id($corr)) : '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$canEdit) {
        flash('danger', $isCancelled ? __('cancel.locked') : ($mode === 'pending' ? __('close_req.already_pending') : __('auth.forbidden')));
        redirect(base_url($viewUrl));
    }
    try {
        if ($isRequestMode) {
            close_request_submit($id, $_POST, $user);
            flash('success', __('close_req.submitted'));
            redirect(base_url($viewUrl));
        }
        close_correspondence($id, $_POST, $user, $_FILES);
        flash('success', $isClosed ? __('close.updated') : __('close.done'));
        redirect(base_url('correspondence/close.php?id=' . $id));
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $corr = array_merge($corr, correspondence_closure_from_request($_POST));
    }
} elseif ($closeReq && ($mode === 'direct' || $mode === 'pending')) {
    $corr = array_merge($corr, array_intersect_key($closeReq['data'], correspondence_closure_from_request([])));
}

$v = static function (string $key, $fallback = '') use ($corr) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists($key, $_POST)) {
        if (str_starts_with($key, 'close_attach_') && $key !== 'close_attach_other_text') {
            return !empty($_POST[$key]) ? 1 : 0;
        }
        return $_POST[$key];
    }
    return $corr[$key] ?? $fallback;
};

$closeDocs = get_closure_documents($id);
$canDeleteCloseDoc = $canEdit || user_can('documents.delete', $user);
$canPrintClose = correspondence_is_closed($corr) && trim((string) ($corr['close_action'] ?? '')) !== '';
$printCloseUrl = base_url('correspondence/close_print.php?id=' . $id);

$pageHeading = $isRequestMode ? __('close_req.page') : __('close.page');
$pageTitle = $pageHeading . ' — ' . $corr['ref_number'];
$pageScripts = dms_page_scripts_with_date_picker($pageScripts ?? '');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title"><?= e($pageHeading) ?></h1>
        <p class="page-sub">
            <span dir="ltr"><?= e($corr['ref_number']) ?></span>
            — <?= e(type_label((string) $corr['type'])) ?>
            — <?= e($corr['subject']) ?>
        </p>
    </div>
    <div class="page-header-actions">
        <?php if ($canPrintClose): ?>
            <a class="btn btn-kff" href="<?= e($printCloseUrl) ?>" target="_blank">
                <i class="bi bi-printer"></i> <?= e(__('close.print')) ?>
            </a>
        <?php else: ?>
            <button type="button" class="btn btn-kff" data-close-print-need-save="<?= e(__('close.print_unavailable')) ?>">
                <i class="bi bi-printer"></i> <?= e(__('close.print')) ?>
            </button>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(base_url($viewUrl)) ?>"><?= e(__('action.back')) ?></a>
    </div>
</div>

<?php if ($error !== ''): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: <?= json_encode(__('common.error'), JSON_UNESCAPED_UNICODE) ?>, text: <?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>, confirmButtonColor: '#9B7D3B' });
            }
        });
    </script>
<?php endif; ?>

<div class="card-panel mb-3">
    <div class="card-header"><i class="bi bi-info-circle me-1"></i><?= e(__('close.corr_section')) ?></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small"><?= e(__('corr.number')) ?></div>
                <div class="fw-semibold" dir="ltr"><?= e($corr['ref_number']) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= e(__('corr.date')) ?></div>
                <div><?= e(format_date($corr['corr_date'])) ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= e(__('corr.department')) ?></div>
                <div><?= e(department_display_name($corr, 'department_name', 'department_name_en') ?: __('common.none')) ?></div>
            </div>
            <div class="col-12">
                <div class="text-muted small"><?= e(__('corr.subject')) ?></div>
                <div><?= e($corr['subject']) ?></div>
            </div>
        </div>
        <p class="admin-form-hint mb-0 mt-3"><?= e(__('close.hint')) ?></p>
    </div>
</div>

<?php if ($isRequestMode): ?>
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-send-check mt-1"></i>
        <div><?= e(str_replace(':dept', $deciderDeptName !== '' ? $deciderDeptName : __('common.none'), __('close_req.request_intro'))) ?></div>
    </div>
<?php elseif ($mode === 'pending' && $closeReq): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-hourglass-split mt-1"></i>
        <div><?= e(str_replace(':dept', $deciderDeptName, __('close_req.pending_intro'))) ?></div>
    </div>
<?php elseif ($closeReq && $mode === 'direct'): ?>
    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-inbox mt-1"></i>
        <div><?= e(str_replace(':name', user_display_name($closeReq, 'requested_by_name', 'requested_by_name_en'), __('close_req.prefilled'))) ?></div>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(base_url('correspondence/close.php?id=' . $id)) ?>" enctype="multipart/form-data"
      <?= $canEdit ? 'data-confirm="' . e($isRequestMode ? __('close_req.confirm') : __('close.confirm')) . '"' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <?php if ($needAdminReason): ?>
    <div class="card-panel mb-3 border-warning">
        <div class="card-header"><i class="bi bi-shield-lock me-1"></i><?= e(__('close_req.admin_reason')) ?></div>
        <div class="card-body">
            <label class="form-label" for="admin_close_reason">
                <?= e(__('close_req.admin_reason')) ?> *
                <i class="bi bi-info-circle text-muted ms-1" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top"
                   title="<?= e(str_replace(':dept', $deciderDeptName, __('close_req.admin_reason_hint'))) ?>"></i>
            </label>
            <textarea name="admin_close_reason" id="admin_close_reason" class="form-control" rows="2" required minlength="5" maxlength="500"><?= e((string) ($_POST['admin_close_reason'] ?? '')) ?></textarea>
        </div>
    </div>
    <?php endif; ?>

    <div class="card-panel mb-3">
        <div class="card-header"><i class="bi bi-check2-square me-1"></i><?= e(__('close.action_section')) ?></div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="close_action"><?= e(__('close.action')) ?> *</label>
                <textarea name="close_action" id="close_action" class="form-control" rows="4" required minlength="3" maxlength="5000"
                          <?= $canEdit ? '' : 'readonly' ?>
                          placeholder="<?= e(__('close.action_ph')) ?>"><?= e((string) $v('close_action')) ?></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="close_decision_no"><?= e(__('close.decision_no')) ?></label>
                    <input type="text" name="close_decision_no" id="close_decision_no" class="form-control" maxlength="100"
                           value="<?= e((string) $v('close_decision_no')) ?>" <?= $canEdit ? '' : 'readonly' ?>>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="close_decision_date-display"><?= e(__('close.decision_date')) ?></label>
                    <?php
                    render_dms_gregorian_date_field([
                        'name' => 'close_decision_date',
                        'id' => 'close_decision_date',
                        'value' => (string) $v('close_decision_date'),
                        'readonly' => !$canEdit,
                    ]);
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-panel mb-3">
        <div class="card-header"><i class="bi bi-flag me-1"></i><?= e(__('close.result_section')) ?></div>
        <div class="card-body">
            <div class="form-label"><?= e(__('close.result')) ?> *</div>
            <?php foreach (correspondence_close_result_codes() as $code): ?>
                <?php $rid = 'close_result_' . $code; ?>
                <div class="d-flex align-items-start gap-2 mb-2">
                    <input class="form-check-input m-0 flex-shrink-0" type="radio" name="close_result" id="<?= e($rid) ?>"
                           value="<?= e($code) ?>"
                           <?= (string) $v('close_result') === $code ? 'checked' : '' ?>
                           <?= $canEdit ? 'required' : 'disabled' ?>>
                    <label class="form-check-label mb-0" for="<?= e($rid) ?>"><?= e(correspondence_close_result_label($code)) ?></label>
                </div>
            <?php endforeach; ?>
            <?php if (!$canEdit && (string) $v('close_result') !== ''): ?>
                <input type="hidden" name="close_result" value="<?= e((string) $v('close_result')) ?>">
            <?php endif; ?>
        </div>
    </div>

    <div class="card-panel mb-3">
        <div class="card-header"><i class="bi bi-paperclip me-1"></i><?= e(__('close.attach_section')) ?></div>
        <div class="card-body">
            <?php
            $attachOpts = [
                'close_attach_decision' => __('close.attach_decision'),
                'close_attach_letter' => __('close.attach_letter'),
                'close_attach_proof' => __('close.attach_proof'),
                'close_attach_other' => __('close.attach_other'),
            ];
            foreach ($attachOpts as $name => $label):
            ?>
                <div class="d-flex align-items-start gap-2 mb-2">
                    <input class="form-check-input m-0 flex-shrink-0" type="checkbox" name="<?= e($name) ?>" id="<?= e($name) ?>"
                           value="1" <?= (int) $v($name, 0) === 1 ? 'checked' : '' ?> <?= $canEdit ? '' : 'disabled' ?>>
                    <label class="form-check-label mb-0" for="<?= e($name) ?>"><?= e($label) ?></label>
                </div>
            <?php endforeach; ?>
            <div class="mt-2 mb-3">
                <label class="form-label" for="close_attach_other_text"><?= e(__('close.attach_other_text')) ?></label>
                <input type="text" name="close_attach_other_text" id="close_attach_other_text" class="form-control" maxlength="255"
                       value="<?= e((string) $v('close_attach_other_text')) ?>" <?= $canEdit ? '' : 'readonly' ?>>
            </div>
            <?php if ($closeDocs): ?>
                <div class="corr-file-list mb-3">
                    <?php foreach ($closeDocs as $doc): ?>
                        <?php
                        $delFormId = 'del-close-doc-' . (int) $doc['id'];
                        $closeDocName = (string) ($doc['file_name'] ?? '');
                        $closeIsPdf = str_contains(strtolower((string) ($doc['file_type'] ?? '')), 'pdf')
                            || str_ends_with(strtolower($closeDocName), '.pdf');
                        ?>
                        <div class="corr-file-row">
                            <div class="corr-file-icon"><i class="bi bi-file-earmark<?= $closeIsPdf ? '-pdf' : '' ?>"></i></div>
                            <div class="corr-file-meta">
                                <?php if (user_can_view_corr_document($corr, $doc, $user)): ?>
                                <a href="<?= e(file_download_url('document', (int) $doc['id'])) ?>" target="_blank"><?= e($closeDocName) ?></a>
                                <?php else: ?>
                                <span class="text-muted" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= e(__('conf.file_locked')) ?>"><i class="bi bi-lock-fill me-1"></i><?= e($closeDocName) ?></span>
                                <?php endif; ?>
                                <span class="dms-file-list-type ms-1"><?= e(dms_upload_file_type_label($closeDocName, (string) ($doc['file_type'] ?? ''))) ?></span>
                                <div class="small text-muted"><?= e(format_datetime($doc['uploaded_at'] ?? null)) ?></div>
                            </div>
                            <?php if ($canDeleteCloseDoc): ?>
                                <button class="btn btn-sm btn-outline-danger" type="submit" form="<?= e($delFormId) ?>"
                                        data-confirm="<?= e(__('corr.delete_attach_confirm')) ?>"><?= e(__('action.delete')) ?></button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($canEdit && !$isRequestMode): ?>
                <?php
                $showEnclosure = false;
                $dmsFile = [
                    'name' => 'close_files[]',
                    'id' => 'close_files',
                    'multiple' => true,
                    'accept' => '.pdf,.jpg,.jpeg,.png,.gif,.webp',
                    'label' => __('close.attach_upload'),
                    'hint' => '',
                    'browse' => __('file.browse'),
                    'placeholder' => __('file.placeholder'),
                    'selected' => __('file.selected'),
                    'wrap_class' => 'mb-0',
                ];
                require __DIR__ . '/../includes/corr_attachments_upload_block.php';
                ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-panel mb-3">
        <div class="card-header"><i class="bi bi-chat-left-text me-1"></i><?= e(__('close.notes_section')) ?></div>
        <div class="card-body">
            <label class="form-label" for="close_notes"><?= e(__('close.notes')) ?></label>
            <textarea name="close_notes" id="close_notes" class="form-control" rows="3" maxlength="2000"
                      <?= $canEdit ? '' : 'readonly' ?>><?= e((string) $v('close_notes')) ?></textarea>
            <?php if ($isClosed): ?>
                <div class="small text-muted mt-3">
                    <?php if (!empty($corr['closed_at'])): ?>
                        <?= e(__('close.at')) ?>: <?= e(format_datetime($corr['closed_at'])) ?>
                    <?php endif; ?>
                    <?php
                    $closedByName = user_display_name($corr, 'closed_by_name', 'closed_by_name_en');
                    if ($closedByName !== ''):
                    ?>
                        · <?= e(__('close.by')) ?>: <?= e($closedByName) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            <?php if ($canEdit && $isRequestMode): ?>
                <button type="submit" class="btn btn-kff">
                    <i class="bi bi-send-check me-1"></i><?= e(__('close_req.submit')) ?>
                </button>
            <?php elseif ($canEdit): ?>
                <button type="submit" class="btn btn-kff">
                    <i class="bi bi-lock me-1"></i><?= e($isClosed ? __('close.submit_update') : __('close.submit')) ?>
                </button>
            <?php endif; ?>
            <?php if ($canPrintClose): ?>
                <a class="btn btn-outline-secondary" href="<?= e($printCloseUrl) ?>" target="_blank">
                    <i class="bi bi-printer me-1"></i><?= e(__('close.print')) ?>
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary" data-close-print-need-save="<?= e(__('close.print_unavailable')) ?>">
                    <i class="bi bi-printer me-1"></i><?= e(__('close.print')) ?>
                </button>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(base_url($viewUrl)) ?>"><?= e(__('action.cancel')) ?></a>
        </div>
</form>
<?php if ($canDeleteCloseDoc): ?>
    <?php foreach ($closeDocs as $doc): ?>
        <form id="del-close-doc-<?= (int) $doc['id'] ?>" method="post" action="<?= e(base_url('documents/delete.php')) ?>" class="d-none">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $doc['id'] ?>">
            <input type="hidden" name="return" value="<?= e('correspondence/close.php?id=' . $id) ?>">
        </form>
    <?php endforeach; ?>
<?php endif; ?>
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-close-print-need-save]');
    if (!btn) return;
    var msg = btn.getAttribute('data-close-print-need-save') || '';
    if (window.DmsSwal && typeof window.DmsSwal.warning === 'function') {
        window.DmsSwal.warning(msg);
    } else if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'warning', text: msg, confirmButtonText: <?= json_encode(__('common.ok'), JSON_UNESCAPED_UNICODE) ?>, confirmButtonColor: '#9B7D3B' });
    } else {
        alert(msg);
    }
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
