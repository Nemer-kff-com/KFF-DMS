<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/correspondence.php';
$user = require_login();
if (!user_can('outgoing.create', $user)) {
    flash('danger', __('corr.no_perm_add_out'));
    redirect(base_url('outgoing/index.php'));
}
$pageTitle = __('corr.outgoing_new');
$type = 'outgoing';
$departments = departments_list();
$categories = categories_list();
$linkables = [];
$prelink = (int) ($_GET['link'] ?? 0);
$corr = null;
$error = '';

/* لا تُمهّد ربط وارد محجوز — الرد بعد اعتماد التوجيه فقط */
if ($prelink > 0 && function_exists('get_correspondence') && function_exists('incoming_approval_is_held')) {
    $prelinkCorr = get_correspondence($prelink);
    if ($prelinkCorr && ($prelinkCorr['type'] ?? '') === 'incoming' && incoming_approval_is_held($prelinkCorr)) {
        $error = __('in_issue_appr.reply_blocked');
        $prelink = 0;
    }
}

$prepOn = function_exists('outgoing_prep_enabled') && outgoing_prep_enabled();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $uploaded = function_exists('dms_normalize_upload_files')
            ? dms_normalize_upload_files($_FILES['attachments'] ?? $_FILES['attachment'] ?? null)
            : [];
        if (!$prepOn && empty($_POST['intend_electronic']) && $uploaded === []) {
            throw new RuntimeException(__('out_prep.attachment_before_review'));
        }
        $id = save_correspondence('outgoing', $_POST, $_FILES['attachments'] ?? $_FILES['attachment'] ?? null, $user);
        $flashMsg = $prepOn ? __('out_prep.saved') : __('corr.saved_out');
        $flashType = 'success';
        if (!$prepOn && function_exists('get_correspondence') && function_exists('outgoing_approval_status_of')) {
            $savedCorr = get_correspondence($id);
            $savedStatus = is_array($savedCorr) ? outgoing_approval_status_of($savedCorr) : '';
            if ($savedStatus === 'pending_review') {
                $flashMsg = __('corr.saved_out_to_reviewer');
            } elseif (in_array($savedStatus, ['pending_sg', 'pending_deputy', 'pending_alternate'], true)) {
                $flashMsg = __('corr.saved_out_to_approver');
            } elseif (in_array($savedStatus, ['with_comms', 'approved'], true)
                || (function_exists('corr_issue_first_destination') && corr_issue_first_destination('outgoing') === 'none')) {
                $flashMsg = __('corr.saved_out_to_comms');
            }
        }
        if ($prepOn && !empty($_POST['out_prep_submit']) && function_exists('outgoing_prep_submit')) {
            try {
                $result = outgoing_prep_submit($id, $user);
                $flashMsg = $result === 'converted' ? outgoing_prep_converted_message($id) : __('out_prep.submitted_ok');
            } catch (Throwable $submitEx) {
                $flashType = 'warning';
                $flashMsg = __('out_prep.saved') . ' — ' . $submitEx->getMessage();
            }
        }
        flash($flashType, $flashMsg);
        redirect(base_url('outgoing/view.php?id=' . $id));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageScripts = dms_page_scripts_with_date_picker($pageScripts ?? '');
require __DIR__ . '/../includes/header.php';
?>
<div class="page-header page-narrow">
    <div>
        <h1 class="page-title"><?= e(__('corr.outgoing_create')) ?></h1>
        <p class="page-sub"><?= e($prepOn ? __('out_prep.create_sub') : str_replace(':sample', corr_ref_sample(''), __('corr.numbering_out'))) ?></p>
    </div>
    <div class="page-header-actions">
        <?php $bulkUi = dms_perm_ui('outgoing.bulk', $user); ?>
        <?php if ($bulkUi === 'allow'): ?>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('outgoing/bulk.php')) ?>">
            <i class="bi bi-people me-1"></i><?= e(__('corr.outgoing_bulk')) ?>
        </a>
        <?php elseif ($bulkUi === 'deny'): ?>
        <?= dms_perm_denied_btn(__('corr.outgoing_bulk'), 'bi-people') ?>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('outgoing/index.php')) ?>">
            <i class="bi <?= dms_is_rtl() ? 'bi-arrow-right' : 'bi-arrow-left' ?> me-1"></i><?= e(__('action.back')) ?>
        </a>
    </div>
</div>

<div class="admin-editor page-narrow">
    <div class="card-panel">
        <div class="card-header">
            <div class="admin-editor-header">
                <span class="admin-editor-icon"><i class="bi bi-send"></i></span>
                <span><?= e(__('corr.outgoing_create')) ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php require __DIR__ . '/../includes/corr_form_error.php'; ?>
            <?php if ($prepOn): ?>
            <div class="alert alert-info py-2 small no-print">
                <i class="bi bi-pencil-square me-1"></i>
                <?= e(__('out_prep.create_hint')) ?>
            </div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" class="admin-form">
                <?= csrf_field() ?>
                <?php require __DIR__ . '/../includes/corr_form_fields.php'; ?>
                <?php
                $attachLabel = __('corr.attach');
                $electronicChecked = !empty($_POST['intend_electronic']);
                $rsvpChecked = !empty($_POST['rsvp_required']);
                $notesValue = (string) ($_POST['notes'] ?? '');
                require __DIR__ . '/../includes/corr_outgoing_attach_options.php';
                ?>
                <?php require __DIR__ . '/../includes/corr_outgoing_letter_fields.php'; ?>

                <div class="corr-issue-decide">
                    <div class="corr-decide-actions">
                    <?php if ($prepOn): ?>
                    <button class="btn btn-kff" type="submit" name="out_prep_submit" value="1" data-keep-label data-confirm="<?= e(__('out_prep.confirm_submit')) ?>">
                        <i class="bi bi-send me-1"></i><?= e(__('out_prep.save_and_submit')) ?>
                    </button>
                    <button class="btn btn-outline-secondary" type="submit" data-keep-label data-confirm="<?= e(__('corr.confirm_save_draft')) ?>">
                        <i class="bi bi-check2 me-1"></i><?= e(__('corr.save_draft')) ?>
                    </button>
                    <?php else: ?>
                    <?php
                    $saveLocked = empty($_POST['intend_electronic']);
                    $saveConfirm = function_exists('corr_create_confirm_message')
                        ? corr_create_confirm_message('outgoing')
                        : __('corr.confirm_save_out');
                    ?>
                    <span id="out_save_gate" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" data-tip-edge="start" data-tip="<?= e(__('out_prep.attachment_before_review')) ?>" title="<?= e(__('out_prep.attachment_before_review')) ?>">
                    <button class="btn btn-kff" type="submit" id="out_save_btn" data-keep-label data-confirm-text="<?= e($saveConfirm) ?>" <?= $saveLocked ? 'disabled' : '' ?> <?= $saveLocked ? '' : 'data-confirm="' . e($saveConfirm) . '"' ?>>
                        <i class="bi bi-check2 me-1"></i><?= e(__('corr.save_out')) ?>
                    </button>
                    </span>
                    <?php endif; ?>
                    <a href="<?= e(base_url('outgoing/index.php')) ?>" class="btn btn-kff-soft" data-keep-label data-confirm="<?= e(__('corr.confirm_cancel_create')) ?>"><?= e(__('action.cancel')) ?></a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $bindInvalid = true; require __DIR__ . '/../includes/corr_entity_email_hint_js.php'; ?>
<?php if (!$prepOn): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var btn = document.getElementById('out_save_btn');
  if (!btn) return;
  var gate = document.getElementById('out_save_gate');
  var electronic = document.getElementById('intend_electronic');
  var input = document.getElementById('attachment');
  var confirmText = btn.getAttribute('data-confirm-text') || '';
  var tipText = gate ? (gate.getAttribute('data-tip') || '') : '';
  function hasFile() {
    return !!(input && input.files && input.files.length);
  }
  function tipOn() {
    if (!gate || !window.bootstrap || !bootstrap.Tooltip) return;
    gate.setAttribute('title', tipText);
    gate.setAttribute('data-bs-toggle', 'tooltip');
    gate.setAttribute('tabindex', '0');
    bootstrap.Tooltip.getOrCreateInstance(gate, {
      customClass: 'corr-tip-start',
      placement: 'top',
      fallbackPlacements: ['top'],
      popperConfig: function (defaults) {
        return Object.assign({}, defaults, { placement: 'top' });
      }
    });
  }
  function tipOff() {
    if (!gate) return;
    if (window.bootstrap && bootstrap.Tooltip) {
      var inst = bootstrap.Tooltip.getInstance(gate);
      if (inst) inst.dispose();
    }
    gate.removeAttribute('title');
    gate.removeAttribute('data-bs-toggle');
    gate.removeAttribute('data-bs-original-title');
  }
  function sync() {
    var ready = (!!electronic && electronic.checked) || hasFile();
    btn.disabled = !ready;
    if (ready) {
      btn.setAttribute('data-confirm', confirmText);
      tipOff();
    } else {
      btn.removeAttribute('data-confirm');
      tipOn();
    }
  }
  document.addEventListener('dms-file-change', sync);
  if (electronic) electronic.addEventListener('change', sync);
  sync();
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
