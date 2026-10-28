<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if (!user_can('outgoing.bulk', $user)) {
    flash('danger', __('corr.no_perm_add_out'));
    redirect(base_url('outgoing/index.php'));
}

$pageTitle = __('bulk.title');
ensure_entity_groups_schema();
$departments = departments_list();
$categories = categories_list();
$groups = entity_groups_list(true, $user);
$priorityRows = priority_levels_list();
$confidentialityRows = confidentiality_levels_list();
$error = '';
$preGroup = (int) ($_GET['group'] ?? $_POST['entity_group_id'] ?? 0);
$selectedIds = array_map('intval', $_POST['entity_ids'] ?? []);

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
    foreach ($selectedIds as $id) {
        if (isset($byId[$id])) {
            $selectedPayloads[] = $byId[$id];
        }
    }
}

$v = static function (string $key, $default = '') {
    return $_POST[$key] ?? $default;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $batchId = save_outgoing_batch($_POST, $_POST['entity_ids'] ?? [], $user, $_FILES['attachments'] ?? $_FILES['attachment'] ?? null);
        if (function_exists('outgoing_prep_enabled') && outgoing_prep_enabled()) {
            flash('success', __('bulk.created') . ' — ' . __('out_prep.saved'));
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
$corr = null;
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('bulk.title')) ?></h1>
        <p class="page-sub"><?= e(__('bulk.subtitle')) ?></p>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-outline-secondary" href="<?= e(base_url('outgoing/batches.php')) ?>">
            <i class="bi bi-collection me-1"></i><?= e(__('bulk.batches_title')) ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('admin/entity_groups.php')) ?>">
            <i class="bi bi-people me-1"></i><?= e(__('nav.entity_groups')) ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('outgoing/index.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-send-plus"></i></span>
                <span><?= e(__('bulk.title')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php require __DIR__ . '/../includes/corr_form_error.php'; ?>
            <?php if (function_exists('outgoing_prep_enabled') && outgoing_prep_enabled()): ?>
                <div class="alert alert-info py-2 small no-print">
                    <i class="bi bi-pencil-square me-1"></i>
                    <?= e(__('out_prep.create_hint')) ?>
                </div>
            <?php endif; ?>
            <?php if (function_exists('outgoing_approval_is_configured') && outgoing_approval_is_configured()): ?>
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
                    default => __('bulk.confirm_comms'),
                };
            }
            ?>
            <form method="post" enctype="multipart/form-data" class="admin-form" data-confirm="<?= e($bulkConfirm) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="entity_id" id="entity_id" value="">

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
                                            'value' => $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) $v('reply_due') : '',
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
                                        <select name="confidentiality" id="confidentiality" class="form-select">
                                            <?php foreach ($confidentialityRows as $c): ?>
                                                <option value="<?= e($c['code']) ?>" <?= (string) $v('confidentiality', 'normal') === (string) $c['code'] ? 'selected' : '' ?>><?= e(bilingual_label($c)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
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

                <div class="admin-form-section">
                    <div class="row g-3">
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
                                    'placeholder' => __('file.placeholder'),
                                    'selected' => __('file.selected'),
                                ];
                                require __DIR__ . '/../includes/corr_attachments_upload_block.php';
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
                                            <input type="checkbox" class="form-check-input" name="intend_electronic" id="intend_electronic" value="1" <?= !empty($_POST['intend_electronic']) ? 'checked' : '' ?>>
                                            <span class="corr-option-title"><?= e(__('corr.intend_electronic')) ?></span>
                                        </label>
                                        <label class="corr-option-card" title="<?= e(__('rsvp.require_hint')) ?>">
                                            <input class="form-check-input" type="checkbox" name="requires_rsvp" id="requires_rsvp" value="1" <?= !empty($_POST['requires_rsvp']) ? 'checked' : '' ?>>
                                            <span class="corr-option-title"><?= e(__('rsvp.require')) ?></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php require __DIR__ . '/../includes/corr_outgoing_letter_fields.php'; ?>

                <div class="admin-form-section admin-form-section-last">
                    <label class="form-label" for="notes"><?= e(__('corr.notes')) ?></label>
                    <textarea name="notes" id="notes" class="form-control" rows="1"><?= e((string) $v('notes')) ?></textarea>
                </div>

                <div class="admin-form-actions">
                    <button class="btn btn-kff" type="submit">
                        <i class="bi bi-send-plus me-1"></i><?= e(__('bulk.save')) ?>
                    </button>
                    <a class="btn btn-outline-secondary" href="<?= e(base_url('outgoing/index.php')) ?>"><?= e(__('action.cancel')) ?></a>
                </div>
            </form>
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
